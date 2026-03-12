class BsTableMpButton {
    tableId = "";
    endpoint = null;
    table = null;
    type = null;
    id = null;
    _searchBound = false;

    constructor(tableId, endpoint) {
        this.endpoint = endpoint;
        this.tableId = tableId;
        this.table = document.getElementById(this.tableId);
        this._onClick = this.onClick.bind(this);
        this._onNewBtnPopupClick = this.onNewBtnPopupClick.bind(this);
        this.initBsTable();
    }

    setType(type) {
        this.type = type;
    }

    setId(id) {
        this.id = id;
    }

    refreshTable(type = null) {
        if (!type) {
            type = this.type;
        }
        $(this.table).bootstrapTable("refresh", { silent: true });
        this.bindButtons();
    }

    getToolBar() {
        const html = `
            <div class="btn-group" id="tblMpButtonToolbar">
                <button class="btn btn-default" type="button" id="newPopup">
                    <span class="material-icons">add</span>
                    <span>Aggiungi Popup</span>
                </button>
            </div>
        `;

        const template = document.createElement("template");
        template.innerHTML = html;

        const toolbar = template.content.cloneNode(true).querySelector("div.btn-group");

        return toolbar;
    }

    async initBsTable() {
        const self = this;

        const container = self.table.parentNode;
        if (!document.getElementById("tblMpButtonToolbar")) {
            const tbar = self.getToolBar();
            container.insertBefore(tbar, self.table);
        }

        $(self.table).bootstrapTable({
            url: self.endpoint,
            method: "post",
            contentType: "application/x-www-form-urlencoded",
            queryParams: function (params) {
                const $search = $(self.table).closest(".bootstrap-table").find(".search input");
                const liveSearch = $search.length ? $search.val() : params.search;
                const searchParams = {
                    ajax: 1,
                    action: "fetchAllPopups",
                    id: self.id,
                    type: self.type,
                    limit: params.limit,
                    offset: params.offset,
                    search: liveSearch == null ? "" : String(liveSearch),
                    sort: params.sort == undefined ? "a.date_add" : params.sort,
                    order: params.order == undefined ? "desc" : params.order,
                    filter: params.filter == undefined ? "" : params.filter,
                    orderId: self.orderId,
                    customerId: self.customerId,
                };

                return searchParams;
            },
            search: false,
            filterControl: true,
            filterControlVisible: true,
            filterControlSearchClear: true,
            showFilterControlSwitch: false,
            searchOnEnterKey: true,
            sortSelectOptions: true,
            serverSort: true,
            sidePagination: "server",
            pagination: true,
            showRefresh: true,
            showColumns: false,
            striped: true,
            condensed: true,
            pageSize: 25,
            pageList: [10, 25, 50, 100, 250, 500],
            locale: "it-IT",
            classes: "table table-bordered table-hover",
            theadClasses: "thead-light",
            showExport: false,
            toolbar: "#tblMpButtonToolbar",
            uniqueId: self.uniqueId,
            detailView: false, // Imposta a true per avere il dettaglio della riga
            detailFormatter: (_, row) => {
                return '<div id="detail-' + row.id_carrier_brt_localita + '">Caricamento...</div>';
            },
            onExpandRow: (_, row, $detail) => {
                //Per ora non serve, ma lasciamo il codice per futura implementazione
                //$details è il contenuto da visualizzare
            },
            iconsPrefix: "icon", // usa Font Awesome invece delle glyphicons
            icons: {
                detailOpen: "icon-plus icon-2x", // icona quando è chiuso
                detailClose: "icon-minus icon-2x", // icona quando è aperto
            },
            onPostBody: function () {
                console.log(`MODULE MPBUTTON - Bootstrap ${self.tableId} Table pronta.`);
                self.fixDropDownPagination();
                self.setBootstrapTableIcons();
                self.bindNewBtnPopup();
                self.bindSearchClear();
                self.hideOldCustomerNotePanel();
            },
            columns: [
                {
                    field: "id_mp_button",
                    title: "ID",
                    align: "left",
                    sortable: true,
                    uniqueId: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return `<span style="font-family:'monospace';">${value}</span>`;
                    },
                },
                {
                    field: "title",
                    title: "Titolo",
                    align: "left",
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return `<span style="font-family:'monospace';">${value}</span>`;
                    },
                },
                {
                    field: "position",
                    title: "Posizione",
                    align: "center",
                    width: "auto",
                    sortable: true,
                    filterControl: "select",
                    filterData: 'json:{"":"Tutti","1":"SINISTRA","2":"DESTRA","3":"ALTO","4":"BASSO","0":"CENTRO","98":"CARRELLO","99":"DESCRIZIONE"}',
                    formatter: function (value, row, index) {
                        // 1:left, 2:right, 3:top, 4:bottom, 0:center, 98: after_cart, 99: desc
                        const positions = {
                            1: "Sinistra",
                            2: "Destra",
                            3: "Alto",
                            4: "Basso",
                            0: "Centro",
                            98: "Carrello",
                            99: "Descrizione",
                        };

                        const currentPosition = positions[value];

                        return `<span class="badge badge-info" title="${currentPosition}">${currentPosition}</span>`;
                    },
                },
                {
                    field: "priority",
                    title: "Priorità",
                    align: "center",
                    width: "auto",
                    sortable: true,
                    filterControl: "input",
                    formatter: function (value, row, index) {
                        return `<pre>${value}</span>`;
                    },
                },
                {
                    field: "delay",
                    title: "Appare dopo",
                    align: "center",
                    sortable: true,
                    filterControl: "input",
                    width: 72,
                    formatter: function (value, row, index) {
                        return value + " sec.";
                    },
                },
                {
                    field: "expire",
                    title: "Scompare dopo",
                    align: "center",
                    class: "text-center",
                    sortable: true,
                    filterControl: "input",
                    width: 72,
                    formatter: function (value, row, index) {
                        return value + " sec.";
                    },
                },
                {
                    field: "date_start",
                    title: "Data inizio",
                    align: "center",
                    width: "auto",
                    sortable: true,
                    filterControl: "datepicker",
                    formatter: function (value, row, index) {
                        return value;
                    },
                },
                {
                    field: "date_end",
                    title: "Data fine",
                    align: "center",
                    sortable: true,
                    filterControl: "datepicker",
                    formatter: function (value, row, index) {
                        return value;
                    },
                },
                {
                    field: "content",
                    title: "Preview",
                    align: "left",
                    sortable: true,
                    formatter: function (value, row, index) {
                        let content = self.unescapeQuotes(row.content);
                        content = content.replace(/<img\b([^>]*)>/gi, (match, attrs) => {
                            if (/\sstyle\s*=\s*['"]/i.test(attrs)) {
                                return `<img${attrs}>`;
                            }

                            return `<img${attrs} style="max-width:100%; height:auto; max-height:100%; object-fit:contain; display:block;">`;
                        });
                        const div = `
                            <div style="max-width: 400px; max-height: 200px; overflow: hidden;">
                                ${content}
                            </div>
                        `;

                        return div;
                    },
                },
                {
                    field: "active",
                    title: "Attivo",
                    align: "center",
                    sortable: true,
                    filterControl: "select",
                    filterData: 'json:{"":"Tutti","1":"SI","0":"NO"}',
                    formatter: function (value, row, index) {
                        if (value == 1) {
                            return `<span class="material-icons text-success">check</span>`;
                        }

                        return `<span class="material-icons text-danger">close</span>`;
                    },
                },
                {
                    field: "date_add",
                    title: "Data creaz",
                    align: "center",
                    sortable: true,
                    filterControl: "datepicker",
                    formatter: function (value, row, index) {
                        return row.date_add;
                    },
                },
                {
                    field: "date_upd",
                    title: "Data modifica",
                    align: "center",
                    sortable: true,
                    filterControl: "datepicker",
                    formatter: function (value, row, index) {
                        return row.date_upd;
                    },
                },
                {
                    field: "action",
                    title: "Azioni",
                    align: "center",
                    width: 128,
                    sortable: false,
                    formatter: function (value, row, index) {
                        return `
                            <div class="d-flex justify-content-center align-items-center gap-2">
                                <button type="button" class="btn btn-default btn-sm" onclick="editButton(${row.id_mp_button})" title="Modifica">
                                    <span class="material-icons">edit</span>
                                </button>
                                <button type="button" class="btn btn-default btn-sm" onclick="deleteButton(${row.id_mp_button})" title="Elimina">
                                    <span class="material-icons">close</span>
                                </button>
                            </div>
                        `;
                    },
                },
            ],
        });
    }

    unescapeQuotes(str) {
        return String(str).replace(/\\(['"])/g, "$1");
    }

    formatAttachments(value, row, index) {
        const component = `
            <grid-attachment
                endpoint="${this.endpoint}"
                idNote="${row.id_mpnote}"
                add-action="addAttachment"
                delete-action="deleteAttachment"
                files="${value}">
            </grid-attachment>
        `;

        return component;
    }

    fixDropDownPagination() {
        $(".fixed-table-pagination .dropdown-toggle")
            .off("click")
            .on("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                const $btn = $(this);
                const $menu = $btn.closest(".btn-group").find(".dropdown-menu");

                $(".fixed-table-pagination .dropdown-menu").not($menu).removeClass("show");
                $menu.toggleClass("show");
            });

        // Normalizza il markup del dropdown page-size a Bootstrap 3
        $(".fixed-table-pagination .btn-group.dropdown").each(function () {
            var $group = $(this);
            var $menuDiv = $group.find("> .dropdown-menu");

            if ($menuDiv.length) {
                // Se non è già <ul>, converti
                if ($menuDiv.prop("tagName") !== "UL") {
                    var $ul = $('<ul class="dropdown-menu" role="menu"></ul>');

                    $menuDiv.find("a").each(function () {
                        var $a = $(this);
                        var $li = $("<li></li>");
                        $a.removeClass("dropdown-item"); // classe BS4/5 inutile qui
                        $li.append($a);
                        $ul.append($li);
                    });

                    $menuDiv.replaceWith($ul);
                }
            }

            // Assicura data-toggle (non data-bs-toggle) e inizializza il plugin
            var $btn = $group.find("> .dropdown-toggle");
            if ($btn.attr("data-bs-toggle") === "dropdown") {
                $btn.removeAttr("data-bs-toggle").attr("data-toggle", "dropdown");
            }
            if (typeof $.fn.dropdown === "function") {
                $btn.dropdown();
            }
        });

        $("button[name=filterControlSwitch]").html("<i class='material-icons'>filter_list</i>");

        $(document)
            .off("click.bs-table-page-size")
            .on("click.bs-table-page-size", function () {
                $(".fixed-table-pagination .dropdown-menu").removeClass("show");
            });
    }

    setBootstrapTableIcons() {
        document.querySelectorAll("button[name=refresh] i").forEach((i) => {
            i.setAttribute("class", "material-icons");
            i.innerHTML = "refresh";
        });

        document.querySelectorAll("button[name=clearSearch] i").forEach((i) => {
            i.setAttribute("class", "material-icons");
            i.innerHTML = "clear";
        });
    }

    bindButtons() {
        this.table.removeEventListener("click", this._onClick);
        this.table.addEventListener("click", this._onClick);
    }

    bindSearchClear() {
        if (this._searchBound) {
            return;
        }

        const $search = $(this.table).closest(".bootstrap-table").find(".search input");
        if (!$search.length) {
            return;
        }

        this._searchBound = true;
        $search.off("input.mpnotesSearchClear");
        $search.on("input.mpnotesSearchClear", (e) => {
            if (String(e.target.value || "") === "") {
                $(this.table).bootstrapTable("resetSearch", "");
            }
        });
    }

    async onClick(e) {
        const self = this;
        const btn = e.target.closest("button");
        const toggle = e.target.closest("span.material-icons");

        if (btn) {
            const action = btn.dataset.action;
            const idNote = btn.dataset.idNote;
            const idOrder = btn.dataset.idOrder;
            const idCustomer = btn.dataset.idCustomer;
            const type = btn.dataset.type;

            if (action === "addAttachment") {
                const dlg = document.getElementById("mpnote-attachments-dialog");
                dlg.open({
                    type,
                    noteId: idNote,
                    orderId: idOrder,
                    customerId: idCustomer,
                    customerName: "",
                    tableId: "bsTblNotes",
                });
                return;
            }

            if (action === "editNote") {
                const response = await self.getNote(idNote);
                let params = null;

                if (response.success) {
                    params = {
                        /*
                        'id' => $this->id,
                        'id_history' => $this->id_history,
                        'type' => $this->type,
                        'reference' => $this->reference,
                        'id_customer' => $this->id_customer,
                        'customer_firstname' => $this->customer_firstname,
                        'customer_lastname' => $this->customer_lastname,
                        'id_order' => $this->id_order,
                        'id_employee' => $this->id_employee,
                        'employee_firstname' => $this->employee_firstname,
                        'employee_lastname' => $this->employee_lastname,
                        'gravity' => $this->gravity,
                        'content' => $this->content,
                        'printable' => (int) $this->printable,
                        'chat' => (int) $this->chat,
                        'deleted' => $this->deleted,
                        'date_add' => $this->date_add,
                        'date_upd' => $this->date_upd,
                        */
                        type: response.data.type,
                        noteId: response.data.id,
                        orderId: response.data.id_order,
                        customerId: response.data.id_customer,
                        customerName: response.data.customer_firstname + " " + response.data.customer_lastname,
                        employeeId: response.data.id_employee,
                        employeeName: response.data.employee_firstname + " " + response.data.employee_lastname,
                        content: response.data.content,
                        printable: response.data.printable,
                        chat: response.data.chat,
                    };
                } else {
                    showErrorMessage("Caricamento nota non riuscito.");
                    return false;
                }

                self.showEditNoteModal(params);
            }

            return;
        }

        if (toggle) {
            const action = toggle.dataset.action;
            const idNote = toggle.dataset.idNote;
            const data = await this.request("toggleAction", {
                toggleAction: action,
                idNote: idNote,
            });

            showNoticeMessage(data?.message || "Operazione non riuscita.");
            this.refreshTable();

            return;
        }
    }

    async request(action, data) {
        const formData = new FormData();
        formData.append("ajax", 1);
        formData.append("action", action);
        Object.entries(data).forEach(([key, value]) => {
            formData.append(key, value);
        });

        const request = await fetch(this.endpoint, {
            method: "POST",
            body: formData,
        });

        if (!request.ok) {
            throw new Error("MPNOTES: fetch: Network response was not ok");
        }

        const response = await request.json();

        return response;
    }

    async getNote(id) {
        const response = await this.request("getNoteDetails", {
            idNote: id,
        });

        return response;
    }

    showEditNoteModal(data) {
        /*
        data:
            type: type,
            noteId: idNote,
            orderId: idOrder,
            customerId: idCustomer,
            customerName: response.data.customer_name,
            employeeId: response.data.id_employee,
            employeeName: response.data.employee_firstname + " " + response.data.employee_lastname,
            content: response.data.content,
            printable: response.data.printable,
            chat: response.data.chat,
        */

        console.log(data);

        const dlg = document.getElementById("mpnote-dialog");

        dlg.open({
            type: data.type,
            noteId: data.noteId,
            orderId: data.orderId,
            customerId: data.customerId,
            customerName: data.customerName,
            employeeId: data.employeeId,
            employeeName: data.employeeName,
            content: data.content,
            printable: data.printable,
            chat: data.chat,
        });
    }

    bindNewBtnPopup() {
        const id = "newPopup";
        const btn = document.getElementById(id);
        if (btn) {
            btn.removeEventListener("click", this._onNewBtnPopupClick);
            btn.addEventListener("click", this._onNewBtnPopupClick);
        }
    }

    onNewBtnPopupClick(e) {
        e.preventDefault();

        const url = new URL(this.endpoint, window.location.origin);
        url.searchParams.set("action", "edit");
        url.searchParams.set("id", "0");
        window.location.href = url.toString();
    }

    hideOldCustomerNotePanel() {
        const panel = document.getElementById("privateNote");
        if (panel) {
            panel.style.display = "none";
        }
    }
}
