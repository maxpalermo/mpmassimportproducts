class WebRequester extends HTMLElement {
    constructor() {
        super();
        this.attachShadow({ mode: "open" });

        this._resolver = null;
        this._dialog = null;
        this._topbar = null;
        this._topbarIcon = null;
        this._topbarTitle = null;
        this._messageEl = null;
        this._btnYes = null;
        this._btnNo = null;
        this._btnYesIcon = null;
        this._btnNoIcon = null;
        this._btnYesText = null;
        this._btnNoText = null;
        this._btnYesKeyhint = null;
        this._btnNoKeyhint = null;

        this._defaultYesText = "";
        this._defaultNoText = "";
        this._defaultYesKeyhint = "";
        this._defaultNoKeyhint = "";

        this._onCancel = null;
        this._onClose = null;
        this._onYes = null;
        this._onNo = null;

        this._ignoreNextClose = false;

        this._isWaiting = false;
    }

    static get observedAttributes() {
        return ["theme"];
    }

    attributeChangedCallback(name, oldValue, newValue) {
        if (name !== "theme" || oldValue === newValue) return;
        this._applyTheme(newValue);
    }

    get theme() {
        return this.getAttribute("theme");
    }

    set theme(value) {
        if (value === null || value === undefined || String(value).trim() === "") {
            this.removeAttribute("theme");
            this._applyTheme(null);
            return;
        }
        this.setAttribute("theme", String(value));
    }

    setTheme(theme) {
        this.theme = theme;
    }

    connectedCallback() {
        if (this.shadowRoot && this.shadowRoot.childNodes.length) return;

        const template = document.createElement("template");
        template.innerHTML = `
            <style>
                :host {
                    --wr-bg: #0b1220;
                    --wr-card: #ffffff;
                    --wr-text: #0f172a;
                    --wr-muted: #64748b;
                    --wr-border: rgba(15, 23, 42, 0.10);
                    --wr-shadow: 0 24px 60px rgba(2, 6, 23, 0.35);
                    --wr-radius: 16px;
                    --wr-focus: 0 0 0 4px rgba(59, 130, 246, 0.20);

                    --soft-info-color: #0ea5e9;
                    --soft-info-bg: #e0f2fe;
                    --soft-info-border: #bae6fd;
                    --soft-info-text: #0369a1;
                    --soft-success-color: #10b981;
                    --soft-success-bg: #d1fae5;
                    --soft-success-border: #a7f3d0;
                    --soft-success-text: #047857;
                    --soft-warning-color: #f59e0b;
                    --soft-warning-bg: #fef3c7;
                    --soft-warning-border: #fde68a;
                    --soft-warning-text: #b45309;
                    --soft-danger-color: #ef4444;
                    --soft-danger-bg: #fee2e2;
                    --soft-danger-border: #fecaca;
                    --soft-danger-text: #b91c1c;

                    --bold-info-color: #3b82f6;
                    --bold-info-bg: #dbeafe;
                    --bold-info-border: #bfdbfe;
                    --bold-info-text: #1e40af;
                    --bold-success-color: #22c55e;
                    --bold-success-bg: #dcfce7;
                    --bold-success-border: #bbf7d0;
                    --bold-success-text: #166534;
                    --bold-warning-color: #f97316;
                    --bold-warning-bg: #ffedd5;
                    --bold-warning-border: #fed7aa;
                    --bold-warning-text: #9a3412;
                    --bold-danger-color: #dc2626;
                    --bold-danger-bg: #fee2e2;
                    --bold-danger-border: #fecaca;
                    --bold-danger-text: #991b1b;

                    --pastel-info-color: #38bdf8;
                    --pastel-info-bg: #f0f9ff;
                    --pastel-info-border: #e0f2fe;
                    --pastel-info-text: #0c4a6e;
                    --pastel-success-color: #4ade80;
                    --pastel-success-bg: #f0fdf4;
                    --pastel-success-border: #dcfce7;
                    --pastel-success-text: #166534;
                    --pastel-warning-color: #fbbf24;
                    --pastel-warning-bg: #fefce8;
                    --pastel-warning-border: #fef9c3;
                    --pastel-warning-text: #854d0e;
                    --pastel-danger-color: #f87171;
                    --pastel-danger-bg: #fef2f2;
                    --pastel-danger-border: #fee2e2;
                    --pastel-danger-text: #991b1b;

                    --elegant-info-color: #2563eb;
                    --elegant-info-bg: #eff6ff;
                    --elegant-info-border: #dbeafe;
                    --elegant-info-text: #1e3a8a;
                    --elegant-success-color: #16a34a;
                    --elegant-success-bg: #f0fdf4;
                    --elegant-success-border: #dcfce7;
                    --elegant-success-text: #14532d;
                    --elegant-warning-color: #eab308;
                    --elegant-warning-bg: #fef9c3;
                    --elegant-warning-border: #fef08a;
                    --elegant-warning-text: #854d0e;
                    --elegant-danger-color: #b91c1c;
                    --elegant-danger-bg: #fef2f2;
                    --elegant-danger-border: #fee2e2;
                    --elegant-danger-text: #7f1d1d;

                    --glass-info-color: #0ea5e9;
                    --glass-info-bg: rgba(14, 165, 233, 0.1);
                    --glass-info-border: rgba(14, 165, 233, 0.2);
                    --glass-info-text: #0369a1;
                    --glass-success-color: #10b981;
                    --glass-success-bg: rgba(16, 185, 129, 0.1);
                    --glass-success-border: rgba(16, 185, 129, 0.2);
                    --glass-success-text: #047857;
                    --glass-warning-color: #f59e0b;
                    --glass-warning-bg: rgba(245, 158, 11, 0.1);
                    --glass-warning-border: rgba(245, 158, 11, 0.2);
                    --glass-warning-text: #b45309;
                    --glass-danger-color: #ef4444;
                    --glass-danger-bg: rgba(239, 68, 68, 0.1);
                    --glass-danger-border: rgba(239, 68, 68, 0.2);
                    --glass-danger-text: #b91c1c;

                    --wr-info-color: var(--elegant-info-color);
                    --wr-info-bg: var(--elegant-info-bg);
                    --wr-info-border: var(--elegant-info-border);
                    --wr-info-text: var(--elegant-info-text);
                    --wr-success-color: var(--elegant-success-color);
                    --wr-success-bg: var(--elegant-success-bg);
                    --wr-success-border: var(--elegant-success-border);
                    --wr-success-text: var(--elegant-success-text);
                    --wr-warning-color: var(--elegant-warning-color);
                    --wr-warning-bg: var(--elegant-warning-bg);
                    --wr-warning-border: var(--elegant-warning-border);
                    --wr-warning-text: var(--elegant-warning-text);
                    --wr-danger-color: var(--elegant-danger-color);
                    --wr-danger-bg: var(--elegant-danger-bg);
                    --wr-danger-border: var(--elegant-danger-border);
                    --wr-danger-text: var(--elegant-danger-text);
                    display: contents;
                }

                @keyframes wr-spin {
                    from { transform: rotate(0deg); }
                    to { transform: rotate(360deg); }
                }

                :host([data-theme="soft"]) {
                    --wr-info-color: var(--soft-info-color);
                    --wr-info-bg: var(--soft-info-bg);
                    --wr-info-border: var(--soft-info-border);
                    --wr-info-text: var(--soft-info-text);
                    --wr-success-color: var(--soft-success-color);
                    --wr-success-bg: var(--soft-success-bg);
                    --wr-success-border: var(--soft-success-border);
                    --wr-success-text: var(--soft-success-text);
                    --wr-warning-color: var(--soft-warning-color);
                    --wr-warning-bg: var(--soft-warning-bg);
                    --wr-warning-border: var(--soft-warning-border);
                    --wr-warning-text: var(--soft-warning-text);
                    --wr-danger-color: var(--soft-danger-color);
                    --wr-danger-bg: var(--soft-danger-bg);
                    --wr-danger-border: var(--soft-danger-border);
                    --wr-danger-text: var(--soft-danger-text);
                }

                :host([data-theme="bold"]) {
                    --wr-info-color: var(--bold-info-color);
                    --wr-info-bg: var(--bold-info-bg);
                    --wr-info-border: var(--bold-info-border);
                    --wr-info-text: var(--bold-info-text);
                    --wr-success-color: var(--bold-success-color);
                    --wr-success-bg: var(--bold-success-bg);
                    --wr-success-border: var(--bold-success-border);
                    --wr-success-text: var(--bold-success-text);
                    --wr-warning-color: var(--bold-warning-color);
                    --wr-warning-bg: var(--bold-warning-bg);
                    --wr-warning-border: var(--bold-warning-border);
                    --wr-warning-text: var(--bold-warning-text);
                    --wr-danger-color: var(--bold-danger-color);
                    --wr-danger-bg: var(--bold-danger-bg);
                    --wr-danger-border: var(--bold-danger-border);
                    --wr-danger-text: var(--bold-danger-text);
                }

                :host([data-theme="pastel"]) {
                    --wr-info-color: var(--pastel-info-color);
                    --wr-info-bg: var(--pastel-info-bg);
                    --wr-info-border: var(--pastel-info-border);
                    --wr-info-text: var(--pastel-info-text);
                    --wr-success-color: var(--pastel-success-color);
                    --wr-success-bg: var(--pastel-success-bg);
                    --wr-success-border: var(--pastel-success-border);
                    --wr-success-text: var(--pastel-success-text);
                    --wr-warning-color: var(--pastel-warning-color);
                    --wr-warning-bg: var(--pastel-warning-bg);
                    --wr-warning-border: var(--pastel-warning-border);
                    --wr-warning-text: var(--pastel-warning-text);
                    --wr-danger-color: var(--pastel-danger-color);
                    --wr-danger-bg: var(--pastel-danger-bg);
                    --wr-danger-border: var(--pastel-danger-border);
                    --wr-danger-text: var(--pastel-danger-text);
                }

                :host([data-theme="elegant"]) {
                    --wr-info-color: var(--elegant-info-color);
                    --wr-info-bg: var(--elegant-info-bg);
                    --wr-info-border: var(--elegant-info-border);
                    --wr-info-text: var(--elegant-info-text);
                    --wr-success-color: var(--elegant-success-color);
                    --wr-success-bg: var(--elegant-success-bg);
                    --wr-success-border: var(--elegant-success-border);
                    --wr-success-text: var(--elegant-success-text);
                    --wr-warning-color: var(--elegant-warning-color);
                    --wr-warning-bg: var(--elegant-warning-bg);
                    --wr-warning-border: var(--elegant-warning-border);
                    --wr-warning-text: var(--elegant-warning-text);
                    --wr-danger-color: var(--elegant-danger-color);
                    --wr-danger-bg: var(--elegant-danger-bg);
                    --wr-danger-border: var(--elegant-danger-border);
                    --wr-danger-text: var(--elegant-danger-text);
                }

                :host([data-theme="glass"]) {
                    --wr-info-color: var(--glass-info-color);
                    --wr-info-bg: var(--glass-info-bg);
                    --wr-info-border: var(--glass-info-border);
                    --wr-info-text: var(--glass-info-text);
                    --wr-success-color: var(--glass-success-color);
                    --wr-success-bg: var(--glass-success-bg);
                    --wr-success-border: var(--glass-success-border);
                    --wr-success-text: var(--glass-success-text);
                    --wr-warning-color: var(--glass-warning-color);
                    --wr-warning-bg: var(--glass-warning-bg);
                    --wr-warning-border: var(--glass-warning-border);
                    --wr-warning-text: var(--glass-warning-text);
                    --wr-danger-color: var(--glass-danger-color);
                    --wr-danger-bg: var(--glass-danger-bg);
                    --wr-danger-border: var(--glass-danger-border);
                    --wr-danger-text: var(--glass-danger-text);
                }

                dialog {
                    padding: 0;
                    border: none;
                    border-radius: var(--wr-radius);
                    box-shadow: var(--wr-shadow);
                    width: min(520px, calc(100vw - 32px));
                    background: var(--wr-card);
                    color: var(--wr-text);
                }

                dialog::backdrop {
                    background: rgba(2, 6, 23, 0.55);
                    backdrop-filter: blur(3px);
                }

                .card {
                    display: grid;
                    gap: 14px;
                }

                .card[data-mode="wait"] .actions {
                    display: none;
                }

                .card[data-mode="ok"] #btnNo {
                    display: none;
                }

                .topbar {
                    height: 44px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0 16px;
                    border-top-left-radius: var(--wr-radius);
                    border-top-right-radius: var(--wr-radius);
                    background: var(--wr-info-color);
                    color: #ffffff;
                    border-bottom: 1px solid rgba(255, 255, 255, 0.16);
                }

                .topbar .left {
                    display: inline-flex;
                    align-items: center;
                    gap: 10px;
                    min-width: 0;
                }

                .topbar .tb-icon {
                    width: 48px;
                    height: 48px;
                    display: grid;
                    margin-top: 8px;
                    place-items: center;
                    color: currentColor;
                    flex: 0 0 28px;
                }

                .topbar .tb-icon svg {
                    width: 32px;
                    height: 32px;
                }

                .topbar .tb-icon svg.wr-spinner {
                    animation: wr-spin 0.9s linear infinite;
                }

                .topbar .tb-title {
                    font-weight: 900;
                    letter-spacing: -0.01em;
                    font-size: 13px;
                    white-space: nowrap;
                    overflow: hidden;
                    text-overflow: ellipsis;
                }

                .topbar[data-type="danger"] {
                    background: var(--wr-danger-color);
                    color: #ffffff;
                }

                .topbar[data-type="warning"] {
                    background: var(--wr-warning-color);
                    color: #ffffff;
                }

                .topbar[data-type="success"] {
                    background: var(--wr-success-color);
                    color: #ffffff;
                }

                .content {
                    padding: 0 20px 20px 20px;
                    display: grid;
                    gap: 14px;
                }

                .header {
                    display: flex;
                    gap: 12px;
                    align-items: flex-start;
                }

                .icon {
                    width: 40px;
                    height: 40px;
                    flex: 0 0 40px;
                    display: grid;
                    place-items: center;
                    border-radius: 12px;
                    background: rgba(59, 130, 246, 0.12);
                    color: rgb(37, 99, 235);
                }

                .icon[data-type="danger"] {
                    background: rgba(239, 68, 68, 0.12);
                    color: rgb(220, 38, 38);
                }

                .icon[data-type="warning"] {
                    background: rgba(245, 158, 11, 0.15);
                    color: rgb(180, 83, 9);
                }

                .icon[data-type="success"] {
                    background: rgba(34, 197, 94, 0.14);
                    color: rgb(21, 128, 61);
                }

                .title {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 700;
                    letter-spacing: -0.01em;
                }

                .message {
                    margin: 0;
                    color: var(--wr-muted);
                    font-size: 14px;
                    line-height: 1.45;
                    white-space: pre-wrap;
                    padding-top: 16px;
                }

                .actions {
                    display: flex;
                    gap: 10px;
                    justify-content: flex-end;
                    margin-top: 4px;
                }

                button {
                    appearance: none;
                    border: 1px solid var(--wr-border);
                    background: #ffffff;
                    color: var(--wr-text);
                    border-radius: 12px;
                    padding: 10px 14px;
                    font-weight: 700;
                    font-size: 14px;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    user-select: none;
                }

                button .btn-icon {
                    display: grid;
                    place-items: center;
                }

                button .btn-icon svg {
                    width: 20px;
                    height: 20px;
                }

                button:focus {
                    outline: none;
                    box-shadow: var(--wr-focus);
                }

                .btn-no {
                    background: #ffffff;
                }

                .btn-yes {
                    border-color: var(--wr-info-color);
                    background: var(--wr-info-color);
                    color: #ffffff;
                }

                .btn-yes[data-type="danger"] {
                    border-color: var(--wr-danger-color);
                    background: var(--wr-danger-color);
                }

                .btn-yes[data-type="warning"] {
                    border-color: var(--wr-warning-color);
                    background: var(--wr-warning-color);
                }

                .btn-yes[data-type="success"] {
                    border-color: var(--wr-success-color);
                    background: var(--wr-success-color);
                }

                .keyhint {
                    font-weight: 700;
                    font-size: 12px;
                    opacity: 0.85;
                    padding: 1px 6px;
                    border-radius: 999px;
                    border: 1px solid rgba(255,255,255,0.35);
                }
            </style>

            <dialog id="dlg">
                <form method="dialog" class="card">
                    <div class="topbar" id="topbar" data-type="info">
                        <div class="left">
                            <div class="tb-icon" aria-hidden="true">
                                <span id="topbarIcon"></span>
                            </div>
                            <div class="tb-title" id="topbarTitle">Conferma</div>
                        </div>
                    </div>

                    <div class="content">
                        <p class="message" id="message"></p>

                        <div class="actions">
                            <button type="button" class="btn-no" id="btnNo" value="no">
                                <span class="btn-icon" id="btnNoIcon" aria-hidden="true"></span>
                                <span id="btnNoText">No</span>
                                <span class="keyhint" id="btnNoKeyhint">Esc</span>
                            </button>
                            <button type="button" class="btn-yes" id="btnYes" value="yes">
                                <span class="btn-icon" id="btnYesIcon" aria-hidden="true"></span>
                                <span id="btnYesText">Si</span>
                                <span class="keyhint" id="btnYesKeyhint">Invio</span>
                            </button>
                        </div>
                    </div>
                </form>
            </dialog>
        `;

        this.shadowRoot.appendChild(template.content.cloneNode(true));

        this._dialog = this.shadowRoot.getElementById("dlg");
        this._topbar = this.shadowRoot.getElementById("topbar");
        this._topbarIcon = this.shadowRoot.getElementById("topbarIcon");
        this._topbarTitle = this.shadowRoot.getElementById("topbarTitle");
        this._messageEl = this.shadowRoot.getElementById("message");
        this._btnYes = this.shadowRoot.getElementById("btnYes");
        this._btnNo = this.shadowRoot.getElementById("btnNo");
        this._btnYesIcon = this.shadowRoot.getElementById("btnYesIcon");
        this._btnNoIcon = this.shadowRoot.getElementById("btnNoIcon");
        this._btnYesText = this.shadowRoot.getElementById("btnYesText");
        this._btnNoText = this.shadowRoot.getElementById("btnNoText");
        this._btnYesKeyhint = this.shadowRoot.getElementById("btnYesKeyhint");
        this._btnNoKeyhint = this.shadowRoot.getElementById("btnNoKeyhint");

        this._defaultYesText = this._btnYesText?.textContent ?? "";
        this._defaultNoText = this._btnNoText?.textContent ?? "";
        this._defaultYesKeyhint = this._btnYesKeyhint?.textContent ?? "";
        this._defaultNoKeyhint = this._btnNoKeyhint?.textContent ?? "";

        this._onYes = () => this._resolve(true);
        this._onNo = () => this._resolve(false);
        this._onCancel = (e) => {
            if (this._isWaiting) {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            this._resolve(false);
        };
        this._onClose = () => {
            if (this._ignoreNextClose) {
                this._ignoreNextClose = false;
                return;
            }
            if (this._isWaiting) return;
            if (this._resolver) this._resolve(false);
        };

        this._btnYes.addEventListener("click", this._onYes);
        this._btnNo.addEventListener("click", this._onNo);
        this._dialog.addEventListener("cancel", this._onCancel);
        this._dialog.addEventListener("close", this._onClose);

        this._setButtonIcons();
        this._applyTheme(this.getAttribute("theme"));
        this._setType("info");
    }

    _setButtonsDefault() {
        const card = this._dialog?.querySelector(".card");
        if (card) card.removeAttribute("data-mode");
        if (this._btnYesText) this._btnYesText.textContent = this._defaultYesText;
        if (this._btnNoText) this._btnNoText.textContent = this._defaultNoText;
        if (this._btnYesKeyhint) this._btnYesKeyhint.textContent = this._defaultYesKeyhint;
        if (this._btnNoKeyhint) this._btnNoKeyhint.textContent = this._defaultNoKeyhint;
    }

    disconnectedCallback() {
        this._btnYes?.removeEventListener("click", this._onYes);
        this._btnNo?.removeEventListener("click", this._onNo);
        this._dialog?.removeEventListener("cancel", this._onCancel);
        this._dialog?.removeEventListener("close", this._onClose);
    }

    _setType(type) {
        const t = String(type ?? "info").toLowerCase();
        const normalized = ["info", "success", "warning", "danger", "confirm", "error"].includes(t) ? t : "info";

        const svgMap = {
            info: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-info-icon lucide-info"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>`,
            success: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-laptop-minimal-check-icon lucide-laptop-minimal-check"><path d="M2 20h20"/><path d="m9 10 2 2 4-4"/><rect x="3" y="4" width="18" height="12" rx="2"/></svg>`,
            warning: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-message-circle-warning-icon lucide-message-circle-warning"><path d="M2.992 16.342a2 2 0 0 1 .094 1.167l-1.065 3.29a1 1 0 0 0 1.236 1.168l3.413-.998a2 2 0 0 1 1.099.092 10 10 0 1 0-4.777-4.719"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>`,
            error: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-octagon-x-icon lucide-octagon-x"><path d="m15 9-6 6"/><path d="M2.586 16.726A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2h6.624a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586z"/><path d="m9 9 6 6"/></svg>`,
            confirm: `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-check-icon lucide-package-check"><path d="M12 22V12"/><path d="m16 17 2 2 4-4"/><path d="M21 11.127V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.729l7 4a2 2 0 0 0 2 .001l1.32-.753"/><path d="M3.29 7 12 12l8.71-5"/><path d="m7.5 4.27 8.997 5.148"/></svg>`,
        };

        const mappedType = normalized === "danger" ? "error" : normalized;
        const topbarSvg = svgMap[mappedType] ?? svgMap.info;

        this._btnYes.dataset.type = normalized;
        if (this._topbar) this._topbar.dataset.type = normalized;
        if (this._topbarIcon) this._topbarIcon.innerHTML = topbarSvg;
    }

    _setWaitIcon() {
        const spinnerSvg = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="wr-spinner"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>`;
        if (this._topbarIcon) this._topbarIcon.innerHTML = spinnerSvg;
    }

    _setButtonIcons() {
        const svgConfirm = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-package-check-icon lucide-package-check"><path d="M12 22V12"/><path d="m16 17 2 2 4-4"/><path d="M21 11.127V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.729l7 4a2 2 0 0 0 2 .001l1.32-.753"/><path d="M3.29 7 12 12l8.71-5"/><path d="m7.5 4.27 8.997 5.148"/></svg>`;
        const svgError = `<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-octagon-x-icon lucide-octagon-x"><path d="m15 9-6 6"/><path d="M2.586 16.726A2 2 0 0 1 2 15.312V8.688a2 2 0 0 1 .586-1.414l4.688-4.688A2 2 0 0 1 8.688 2h6.624a2 2 0 0 1 1.414.586l4.688 4.688A2 2 0 0 1 22 8.688v6.624a2 2 0 0 1-.586 1.414l-4.688 4.688a2 2 0 0 1-1.414.586H8.688a2 2 0 0 1-1.414-.586z"/><path d="m9 9 6 6"/></svg>`;
        if (this._btnYesIcon) this._btnYesIcon.innerHTML = svgConfirm;
        if (this._btnNoIcon) this._btnNoIcon.innerHTML = svgError;
    }

    _applyTheme(theme) {
        const t = String(theme ?? "")
            .trim()
            .toLowerCase();
        const allowed = new Set(["soft", "bold", "pastel", "elegant", "glass"]);
        if (!t || !allowed.has(t)) {
            delete this.dataset.theme;
            return;
        }
        this.dataset.theme = t;
    }

    _resolve(value) {
        const resolver = this._resolver;
        this._resolver = null;
        try {
            this._ignoreNextClose = true;
            this._dialog?.close();
        } catch {
            // ignore
        }
        if (resolver) resolver(!!value);
    }

    confirm({ title = "Conferma", message = "", type = "info", theme = undefined, html = false } = {}) {
        if (!this._dialog) this.connectedCallback();
        if (this._resolver) {
            return Promise.reject(new Error("WebRequester: another request is already pending"));
        }
        if (this._isWaiting) {
            return Promise.reject(new Error("WebRequester: wait mode is active"));
        }

        this._setButtonsDefault();
        if (theme !== undefined) this._applyTheme(theme);
        const nextTitle = String(title ?? "");
        if (this._topbarTitle) this._topbarTitle.textContent = nextTitle;
        const nextMessage = String(message ?? "");
        if (html) this._messageEl.innerHTML = nextMessage;
        else this._messageEl.textContent = nextMessage;
        this._setType(type);

        return new Promise((resolve) => {
            this._resolver = resolve;
            this._dialog.showModal();

            setTimeout(() => {
                try {
                    this._btnYes?.focus();
                } catch {
                    // ignore
                }
            }, 0);
        });
    }

    ok({ title = "Avviso", message = "", type = "success", theme = undefined, html = false, okText = "OK" } = {}) {
        if (!this._dialog) this.connectedCallback();
        if (this._resolver) {
            return Promise.reject(new Error("WebRequester: another request is already pending"));
        }
        if (this._isWaiting) {
            return Promise.reject(new Error("WebRequester: wait mode is active"));
        }

        this._setButtonsDefault();
        const card = this._dialog?.querySelector(".card");
        if (card) card.dataset.mode = "ok";
        if (this._btnYesText) this._btnYesText.textContent = String(okText ?? "OK");

        if (theme !== undefined) this._applyTheme(theme);
        if (this._topbarTitle) this._topbarTitle.textContent = String(title ?? "");
        const nextMessage = String(message ?? "");
        if (html) this._messageEl.innerHTML = nextMessage;
        else this._messageEl.textContent = nextMessage;
        this._setType(type);

        return new Promise((resolve) => {
            this._resolver = () => resolve();
            this._dialog.showModal();

            setTimeout(() => {
                try {
                    this._btnYes?.focus();
                } catch {
                    // ignore
                }
            }, 0);
        });
    }

    doWait(title = "Attendere", message = "Operazione in corso...", type = "info", html = false, theme = "soft") {
        if (!this._dialog) this.connectedCallback();
        if (this._resolver) {
            return Promise.reject(new Error("WebRequester: another request is already pending"));
        }

        this._isWaiting = true;
        if (theme !== undefined) this._applyTheme(theme);
        if (this._topbarTitle) this._topbarTitle.textContent = String(title ?? "");
        const nextMessage = String(message ?? "");
        if (html) this._messageEl.innerHTML = nextMessage;
        else this._messageEl.textContent = nextMessage;

        const card = this._dialog?.querySelector(".card");
        if (card) card.dataset.mode = "wait";

        this._setType(type);
        this._setWaitIcon();

        try {
            if (!this._dialog.open) this._dialog.showModal();
        } catch {
            // ignore
        }

        return Promise.resolve();
    }

    hideWait() {
        if (!this._dialog) return;
        this._isWaiting = false;
        const card = this._dialog?.querySelector(".card");
        if (card) card.removeAttribute("data-mode");
        try {
            this._ignoreNextClose = true;
            this._dialog.close();
        } catch {
            // ignore
        }
    }

    _normalizeOpts(opts) {
        if (opts && typeof opts === "object") return opts;
        if (typeof opts === "string") return { message: opts };
        return {};
    }

    info(opts = {}) {
        const o = this._normalizeOpts(opts);
        const title = o.title ?? "Informazione";
        return this.confirm({ ...o, title, type: "info" });
    }

    warning(opts = {}) {
        const o = this._normalizeOpts(opts);
        const title = o.title ?? "Attenzione";
        return this.confirm({ ...o, title, type: "warning" });
    }

    danger(opts = {}) {
        const o = this._normalizeOpts(opts);
        const title = o.title ?? "Errore";
        return this.confirm({ ...o, title, type: "danger" });
    }

    error(opts = {}) {
        const o = this._normalizeOpts(opts);
        const title = o.title ?? "Errore";
        return this.confirm({ ...o, title, type: "danger" });
    }

    success(opts = {}) {
        const o = this._normalizeOpts(opts);
        const title = o.title ?? "Operazione completata";
        return this.confirm({ ...o, title, type: "success" });
    }

    static _getSingleton() {
        let el = document.querySelector("web-requester");
        if (!el) {
            el = document.createElement("web-requester");
            document.body.appendChild(el);
        }
        return el;
    }

    static confirm(opts) {
        return WebRequester._getSingleton().confirm(opts);
    }

    static info(opts) {
        return WebRequester._getSingleton().info(opts);
    }

    static warning(opts) {
        return WebRequester._getSingleton().warning(opts);
    }

    static danger(opts) {
        return WebRequester._getSingleton().danger(opts);
    }

    static error(opts) {
        return WebRequester._getSingleton().error(opts);
    }

    static success(opts) {
        return WebRequester._getSingleton().success(opts);
    }

    static ok(opts) {
        const o = WebRequester._getSingleton()._normalizeOpts(opts);
        return WebRequester._getSingleton().ok(o);
    }

    static alert(opts) {
        const o = WebRequester._getSingleton()._normalizeOpts(opts);
        return WebRequester._getSingleton().ok(o);
    }

    static doWait(title = "Attendere", message = "Operazione in corso...", type = "info", html = false, theme = "soft") {
        return WebRequester._getSingleton().doWait(title, message, type, html, theme);
    }

    static hideWait() {
        return WebRequester._getSingleton().hideWait();
    }
}

if (!customElements.get("web-requester")) {
    customElements.define("web-requester", WebRequester);
}

window.WebRequester = WebRequester;

async function doRequest(title, message, type = "info", html = false, theme = "soft") {
    const value = await WebRequester.confirm({
        title: title || "Conferma",
        message: message,
        type: type,
        theme: theme,
        html: html,
    });

    return value;
}

function initRequester() {
    wr = document.createElement("web-requester");
    document.body.appendChild(wr);

    WebRequester._getSingleton().setTheme("soft");
}
