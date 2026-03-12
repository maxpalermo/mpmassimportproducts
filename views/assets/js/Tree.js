class MpJsonTree extends HTMLElement {
    static get observedAttributes() {
        return ["src", "selection-mode", "multi", "disabled"];
    }

    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        this._data = null;
        this._nodes = new Map();
        this._selected = new Set();
        this._expanded = new Set();
        this._searchTerm = "";
        this._showSelectedOnly = false;
        this._autoSelectedOnlyDone = false;
        this._selectionMode = "multi";
        this._disabled = false;
        this._rootId = null;

        this._onClick = this._onClick.bind(this);
        this._onInput = this._onInput.bind(this);
        this._onKeyDown = this._onKeyDown.bind(this);
    }

    connectedCallback() {
        this._upgradeProperty("src");
        this._upgradeProperty("selectionMode");
        this._upgradeProperty("multi");
        this._upgradeProperty("disabled");

        this._applyAttributes();
        this._renderShell();
        this._bind();

        const src = this.getAttribute("src");
        if (src) {
            this.load(src);
        }
    }

    attributeChangedCallback() {
        if (!this.shadowRoot) return;
        this._applyAttributes();
        this._syncToolbarState();
    }

    get selectionMode() {
        return this._selectionMode;
    }

    set selectionMode(mode) {
        this.setAttribute("selection-mode", String(mode));
    }

    get multi() {
        return this._selectionMode === "multi";
    }

    set multi(v) {
        if (v) {
            this.setAttribute("multi", "");
            this.setAttribute("selection-mode", "multi");
        } else {
            this.removeAttribute("multi");
            this.setAttribute("selection-mode", "single");
        }
    }

    get disabled() {
        return this._disabled;
    }

    set disabled(v) {
        if (v) {
            this.setAttribute("disabled", "");
        } else {
            this.removeAttribute("disabled");
        }
    }

    async load(input) {
        if (typeof input === "string") {
            const url = input;
            const res = await fetch(url, { credentials: "same-origin" });
            if (!res.ok) {
                throw new Error(`Failed to load JSON (${res.status})`);
            }
            const data = await res.json();
            this.setData(data);
            return;
        }

        this.setData(input);
    }

    setData(data) {
        this._data = data;
        this._rebuildIndex();

        if (!this._autoSelectedOnlyDone && this._selected.size > 0) {
            this._showSelectedOnly = true;
            this._expandToSelected();
            this._autoSelectedOnlyDone = true;
        }

        this._renderTree();
        this._syncToolbarState();
    }

    addNode({ path, label, value, selected } = {}) {
        if (!this._data || typeof this._data !== "object") {
            this._data = {};
        }

        const parts = this._normalizePath(path);
        const leafLabel = String(label ?? "").trim();
        if (!leafLabel) return;

        let cursor = this._data;
        for (const p of parts) {
            if (!cursor[p] || typeof cursor[p] !== "object") {
                cursor[p] = {};
            }
            cursor = cursor[p];
        }

        cursor[leafLabel] = {
            value: value,
            selected: selected ? 1 : 0,
        };

        this.setData(this._data);
    }

    getSelectedValues() {
        const values = [];
        for (const id of this._selected) {
            const n = this._nodes.get(id);
            if (!n) continue;
            values.push(n.value);
        }
        return values;
    }

    getSelectedItems() {
        const items = [];
        for (const id of this._selected) {
            const n = this._nodes.get(id);
            if (!n) continue;
            items.push({
                id: n.id,
                path: n.path,
                label: n.label,
                value: n.value,
            });
        }
        return items;
    }

    clearSelection() {
        if (this._selected.size === 0) return;
        this._selected.clear();
        if (this._showSelectedOnly) {
            this._expandToSelected();
            this._renderTree();
        } else this._updateSelectionUI();
        this._emitSelectionChange();
    }

    selectAll() {
        if (this._selectionMode !== "multi") return;
        for (const [id, node] of this._nodes.entries()) {
            if (!node.isLeaf) continue;
            if (this._isVisibleBySearch(id)) {
                this._selected.add(id);
            }
        }
        if (this._showSelectedOnly) {
            this._expandToSelected();
            this._renderTree();
        } else this._updateSelectionUI();
        this._emitSelectionChange();
    }

    expandAll() {
        for (const [id, node] of this._nodes.entries()) {
            if (!node.isLeaf) {
                this._expanded.add(id);
            }
        }
        this._renderTree();
    }

    collapseAll() {
        this._expanded.clear();
        if (this._rootId) this._expanded.add(this._rootId);
        this._renderTree();
    }

    _upgradeProperty(prop) {
        if (Object.prototype.hasOwnProperty.call(this, prop)) {
            const value = this[prop];
            delete this[prop];
            this[prop] = value;
        }
    }

    _applyAttributes() {
        const mode = (this.getAttribute("selection-mode") || "").toLowerCase();
        if (mode === "single" || mode === "multi") {
            this._selectionMode = mode;
        } else {
            this._selectionMode = this.hasAttribute("multi") ? "multi" : "multi";
        }

        this._disabled = this.hasAttribute("disabled");

        if (this._selectionMode === "single" && this._selected.size > 1) {
            const first = this._selected.values().next().value;
            this._selected.clear();
            if (first) this._selected.add(first);
        }
    }

    _renderShell() {
        const root = document.createElement("div");
        root.className = "root";
        root.innerHTML = `
            <style>
                :host{display:block;contain:content;font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#111827;width:var(--mp-json-tree-width, auto);min-width:var(--mp-json-tree-min-width, 0);max-width:var(--mp-json-tree-max-width, 100%);box-sizing:border-box}
                *,*::before,*::after{box-sizing:border-box}
                .root{width:100%;border:1px solid #e5e7eb;border-radius:12px;background:#fff;box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden;box-sizing:border-box}
                .toolbar{display:flex;gap:8px;align-items:center;justify-content:space-between;padding:10px 10px;border-bottom:1px solid #e5e7eb;background:linear-gradient(#ffffff,#fafafa)}
                .left{display:flex;gap:8px;align-items:center;min-width:0;flex:1}
                .right{display:flex;gap:8px;align-items:center;flex:0 0 auto}
                .search{flex:1;min-width:160px;position:relative}
                input[type="search"]{width:100%;padding:8px 10px;border:1px solid #d1d5db;border-radius:10px;outline:none;font-size:13px;background:#fff}
                input[type="search"]:focus{border-color:#60a5fa;box-shadow:0 0 0 3px rgba(96,165,250,.25)}
                button{border:1px solid #d1d5db;background:#fff;border-radius:10px;padding:7px 10px;font-size:12px;cursor:pointer;color:#111827}
                button:hover{background:#f9fafb}
                button:active{background:#f3f4f6}
                button[disabled]{opacity:.5;cursor:not-allowed}
                button[data-state="on"]{background:#eef2ff;border-color:#c7d2fe;color:#3730a3}
                .icon{display:inline-flex;align-items:center;justify-content:center;width:16px;height:16px}
                .icon svg{width:16px;height:16px;display:block;stroke:currentColor;fill:none;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
                .body{max-height:var(--mp-json-tree-max-height, 320px);min-height:var(--mp-json-tree-min-height, auto);overflow:auto;padding:8px}
                .tree{list-style:none;margin:0;padding:0}
                .node{margin:0}
                .row{display:flex;align-items:center;gap:8px;border-radius:10px;padding:6px 8px;user-select:none}
                .row:hover{background:#f9fafb}
                .twisty{width:18px;height:18px;display:grid;place-items:center;border-radius:6px;color:#6b7280}
                .twisty:hover{background:#eef2ff;color:#4338ca}
                .label{font-size:13px;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1}
                .badge{font-size:11px;color:#6b7280;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:999px;padding:1px 7px}
                .check{width:16px;height:16px;border-radius:5px;border:1px solid #d1d5db;background:#fff;display:grid;place-items:center;flex:0 0 auto}
                .check[data-state="on"]{background:#16a34a;border-color:#16a34a}
                .check[data-state="on"] .mark{opacity:1}
                .mark{width:6px;height:10px;opacity:0;background:transparent;border-right:3px solid #fff;border-bottom:3px solid #fff;transform:rotate(45deg);border-radius:0}
                .children{list-style:none;margin:0;padding:0 0 0 22px}
                .empty{padding:14px 12px;color:#6b7280;font-size:13px}
            </style>
            <div class="toolbar" part="toolbar">
                <div class="left">
                    <div class="search">
                        <input part="search" type="search" placeholder="Cerca..." />
                    </div>
                </div>
                <div class="right">
                    <button type="button" data-cmd="expand" title="Espandi tutto"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3H3v5"/><path d="M3 3l7 7"/><path d="M16 3h5v5"/><path d="M21 3l-7 7"/><path d="M8 21H3v-5"/><path d="M3 21l7-7"/><path d="M16 21h5v-5"/><path d="M21 21l-7-7"/></svg></span></button>
                    <button type="button" data-cmd="collapse" title="Comprimi tutto"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M8 3H3v5"/><path d="M10 10L3 3"/><path d="M16 3h5v5"/><path d="M14 10l7-7"/><path d="M8 21H3v-5"/><path d="M10 14l-7 7"/><path d="M16 21h5v-5"/><path d="M14 14l7 7"/></svg></span></button>
                    <button type="button" data-cmd="selectAll" title="Seleziona tutto"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/><path d="M7 6h0"/></svg></span></button>
                    <button type="button" data-cmd="clear" title="Togli selezione"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 6l12 12"/><path d="M18 6L6 18"/></svg></span></button>
                    <button type="button" data-cmd="toggleSelected" data-state="off" title="Mostra/Nasconde solo selezionati"><span class="icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg></span></button>
                </div>
            </div>
            <div class="body" part="body">
                <ul class="tree" part="tree"></ul>
                <div class="empty" part="empty" hidden>Nessun elemento</div>
            </div>
        `;
        this.shadowRoot.innerHTML = "";
        this.shadowRoot.appendChild(root);
    }

    _bind() {
        const root = this.shadowRoot.querySelector(".root");
        root.addEventListener("click", this._onClick);
        const input = this.shadowRoot.querySelector('input[type="search"]');
        input.addEventListener("input", this._onInput);
        input.addEventListener("keydown", this._onKeyDown);
        this._syncToolbarState();
    }

    _syncToolbarState() {
        const multi = this._selectionMode === "multi";
        const disabled = this._disabled;

        this.shadowRoot.querySelector('button[data-cmd="expand"]').disabled = disabled;
        this.shadowRoot.querySelector('button[data-cmd="collapse"]').disabled = disabled;
        this.shadowRoot.querySelector('button[data-cmd="clear"]').disabled = disabled;
        this.shadowRoot.querySelector('button[data-cmd="selectAll"]').disabled = disabled || !multi;

        const toggleBtn = this.shadowRoot.querySelector('button[data-cmd="toggleSelected"]');
        if (toggleBtn) {
            toggleBtn.disabled = disabled;
            toggleBtn.setAttribute("data-state", this._showSelectedOnly ? "on" : "off");
        }

        const input = this.shadowRoot.querySelector('input[type="search"]');
        input.disabled = disabled;
    }

    _onKeyDown(e) {
        if (e.key === "Escape") {
            const input = e.currentTarget;
            if (input.value) {
                input.value = "";
                this._searchTerm = "";
                this._renderTree();
            }
        }
    }

    _onInput(e) {
        this._searchTerm = String(e.currentTarget.value || "")
            .trim()
            .toLowerCase();
        this._renderTree();
    }

    _onClick(e) {
        if (this._disabled) return;

        const target = e.target;
        const cmdBtn = target.closest("button[data-cmd]");
        if (cmdBtn) {
            const cmd = cmdBtn.getAttribute("data-cmd");
            if (cmd === "expand") this.expandAll();
            if (cmd === "collapse") this.collapseAll();
            if (cmd === "selectAll") this.selectAll();
            if (cmd === "clear") this.clearSelection();
            if (cmd === "toggleSelected") {
                this._showSelectedOnly = !this._showSelectedOnly;
                if (this._showSelectedOnly) {
                    this._expandToSelected();
                }
                this._syncToolbarState();
                this._renderTree();
            }
            return;
        }

        const twisty = target.closest(".twisty");
        if (twisty) {
            const id = twisty.getAttribute("data-id");
            const n = this._nodes.get(id);
            if (!n || n.isLeaf) return;
            if (this._expanded.has(id)) this._expanded.delete(id);
            else this._expanded.add(id);
            this._renderTree();
            return;
        }

        const row = target.closest(".row");
        if (!row) return;
        const id = row.getAttribute("data-id");
        const n = this._nodes.get(id);
        if (!n) return;

        if (!n.isLeaf) {
            if (this._expanded.has(id)) this._expanded.delete(id);
            else this._expanded.add(id);
            this._renderTree();
            return;
        }

        this._toggleSelect(id);
    }

    _toggleSelect(id) {
        const already = this._selected.has(id);
        if (this._selectionMode === "single") {
            this._selected.clear();
            if (!already) this._selected.add(id);
        } else {
            if (already) this._selected.delete(id);
            else this._selected.add(id);
        }

        if (this._showSelectedOnly) {
            this._expandToSelected();
            this._renderTree();
        } else this._updateSelectionUI();
        this._emitSelectionChange();
    }

    _emitSelectionChange() {
        this.dispatchEvent(
            new CustomEvent("selection-change", {
                bubbles: true,
                composed: true,
                detail: {
                    values: this.getSelectedValues(),
                    items: this.getSelectedItems(),
                },
            }),
        );
    }

    _rebuildIndex() {
        this._nodes.clear();
        this._selected.clear();
        this._expanded.clear();

        const mkId = (() => {
            let i = 0;
            return () => `n${++i}`;
        })();

        const walk = (obj, parentId, pathParts) => {
            if (!obj || typeof obj !== "object") return;
            const keys = Object.keys(obj);
            for (const rawKey of keys) {
                const key = String(rawKey);
                const v = obj[rawKey];
                const isObj = v && typeof v === "object";
                const isLeaf = isObj && Object.prototype.hasOwnProperty.call(v, "value");
                const nodeId = mkId();
                const nextPath = [...pathParts, key];

                const node = {
                    id: nodeId,
                    parentId: parentId || null,
                    label: key,
                    path: nextPath.join(" / "),
                    isLeaf: Boolean(isLeaf),
                    value: isLeaf ? v.value : null,
                    selected: isLeaf ? Boolean(v.selected) : false,
                    children: [],
                };

                this._nodes.set(nodeId, node);

                if (parentId) {
                    const parent = this._nodes.get(parentId);
                    if (parent) parent.children.push(nodeId);
                }

                if (node.isLeaf) {
                    if (node.selected) this._selected.add(nodeId);
                } else if (isObj) {
                    walk(v, nodeId, nextPath);
                }
            }
        };

        const rootId = mkId();
        this._nodes.set(rootId, {
            id: rootId,
            parentId: null,
            label: "ROOT",
            path: "ROOT",
            isLeaf: false,
            value: null,
            selected: false,
            children: [],
            _isRoot: true,
        });
        this._rootId = rootId;

        walk(this._data, rootId, []);
        this._expanded.add(rootId);

        if (this._selectionMode === "single" && this._selected.size > 1) {
            const first = this._selected.values().next().value;
            this._selected.clear();
            if (first) this._selected.add(first);
        }
    }

    _renderTree() {
        const list = this.shadowRoot.querySelector("ul.tree");
        const empty = this.shadowRoot.querySelector(".empty");
        list.innerHTML = "";

        const root = this._rootId ? this._nodes.get(this._rootId) : null;
        if (!root || !this._data || typeof this._data !== "object") {
            empty.hidden = false;
            return;
        }

        const visibleTop = root.children.filter((id) => this._isVisible(id));
        if (visibleTop.length === 0) {
            empty.hidden = false;
            return;
        }

        empty.hidden = true;
        for (const childId of visibleTop) {
            list.appendChild(this._renderNode(childId));
        }

        this._updateSelectionUI();
    }

    _renderNode(id) {
        const node = this._nodes.get(id);
        const li = document.createElement("li");
        li.className = "node";

        const isExpanded = this._expanded.has(id);
        const hasChildren = !node.isLeaf && node.children.length > 0;
        const showChildren = hasChildren && (isExpanded || this._searchTerm);

        const row = document.createElement("div");
        row.className = "row";
        row.setAttribute("data-id", node.id);
        row.setAttribute("title", node.path);

        const twisty = document.createElement("div");
        twisty.className = "twisty";
        twisty.setAttribute("data-id", node.id);
        twisty.textContent = node.isLeaf ? "" : isExpanded ? "▾" : "▸";

        const check = document.createElement("div");
        check.className = "check";
        check.setAttribute("data-id", node.id);
        check.setAttribute("data-state", this._selected.has(node.id) ? "on" : "off");

        const mark = document.createElement("div");
        mark.className = "mark";
        check.appendChild(mark);

        const label = document.createElement("div");
        label.className = "label";
        label.textContent = node.label;

        row.appendChild(twisty);
        row.appendChild(check);
        row.appendChild(label);

        if (!node.isLeaf) {
            const badge = document.createElement("div");
            badge.className = "badge";
            badge.textContent = String(node.children.filter((cid) => this._nodes.get(cid)?.isLeaf).length);
            row.appendChild(badge);
        }

        li.appendChild(row);

        if (showChildren) {
            const ul = document.createElement("ul");
            ul.className = "children";
            const visibleChildren = node.children.filter((cid) => this._isVisible(cid));
            for (const cid of visibleChildren) {
                ul.appendChild(this._renderNode(cid));
            }
            li.appendChild(ul);
        }

        return li;
    }

    _updateSelectionUI() {
        const checks = this.shadowRoot.querySelectorAll(".check");
        checks.forEach((el) => {
            const id = el.getAttribute("data-id");
            el.setAttribute("data-state", this._selected.has(id) ? "on" : "off");
        });
    }

    _normalizePath(path) {
        if (!path) return [];
        if (Array.isArray(path)) {
            return path.map((p) => String(p).trim()).filter(Boolean);
        }
        return String(path)
            .split("/")
            .map((p) => p.trim())
            .filter(Boolean);
    }

    _isVisibleBySearch(id) {
        if (!this._searchTerm) return true;
        const node = this._nodes.get(id);
        if (!node) return false;

        const term = this._searchTerm;
        const labelMatch = node.label.toLowerCase().includes(term);
        const pathMatch = node.path.toLowerCase().includes(term);
        if (labelMatch || pathMatch) return true;

        if (!node.isLeaf) {
            for (const cid of node.children) {
                if (this._isVisibleBySearch(cid)) return true;
            }
        }

        return false;
    }

    _isVisible(id) {
        if (!this._isVisibleBySearch(id)) return false;
        if (!this._showSelectedOnly) return true;

        const node = this._nodes.get(id);
        if (!node) return false;
        if (node.isLeaf) return this._selected.has(id);

        for (const cid of node.children) {
            if (this._isVisible(cid)) return true;
        }
        return false;
    }

    _expandToSelected() {
        if (!this._rootId) return;

        this._expanded.clear();
        this._expanded.add(this._rootId);

        for (const id of this._selected) {
            let cursor = this._nodes.get(id);
            while (cursor && cursor.parentId) {
                this._expanded.add(cursor.parentId);
                cursor = this._nodes.get(cursor.parentId);
            }
        }
    }
}

if (!customElements.get("mp-json-tree")) {
    customElements.define("mp-json-tree", MpJsonTree);
}
