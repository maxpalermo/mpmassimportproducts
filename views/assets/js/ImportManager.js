(function (window) {
    "use strict";

    function ImportManager(options) {
        this.options = options || {};
        this.state = {
            running: false,
            stopped: false,
            offset: 0,
            limit: this.options.limit || 25,
            resetProducts: [],
            stats: {
                processed: 0,
                imported: 0,
                skipped: 0,
                errors: 0,
            },
            chunks: 0,
        };
        this._xhr = null;
        this._els = null;
        this._styleInjected = false;
    }

    ImportManager.prototype.start = function () {
        if (this.state.running) {
            return;
        }

        this.state.running = true;
        this.state.stopped = false;
        this.state.offset = this.options.offset || 0;
        this.state.limit = this.options.limit || 25;
        this.state.resetProducts = this.options.resetProducts || [];
        this.state.stats = { processed: 0, imported: 0, skipped: 0, errors: 0 };
        this.state.chunks = 0;

        this._ensureUi();
        this._open();
        this._render();
        this._appendLog("Import avviato…");
        this._tick();
    };

    ImportManager.prototype.stop = function () {
        if (!this.state.running) {
            return;
        }
        this.state.stopped = true;
        if (this._xhr && this._xhr.abort) {
            try {
                this._xhr.abort();
            } catch (e) {}
        }
        this._xhr = null;
        this._appendLog("Interruzione richiesta. Attendo stop…");
        this._render();
    };

    ImportManager.prototype.close = function () {
        if (this.state.running) {
            return;
        }
        this._close();
    };

    ImportManager.prototype._tick = function () {
        var self = this;
        if (!this.state.running) {
            return;
        }
        if (this.state.stopped) {
            this.state.running = false;
            this._setStatus("Interrotto");
            this._setProgressMode("stopped");
            this._enableClose(true);
            this._render();
            return;
        }

        this._setStatus("In corso…");
        this._setProgressMode("indeterminate");
        this._enableClose(false);

        this._requestChunk(this.state.offset, this.state.limit, this.state.resetProducts)
            .then(function (res) {
                if (self.state.stopped) {
                    self._tick();
                    return;
                }

                self.state.chunks += 1;

                if (res && res.stats) {
                    self.state.stats.processed += res.stats.processed || 0;
                    self.state.stats.imported += res.stats.imported || 0;
                    self.state.stats.skipped += res.stats.skipped || 0;
                    self.state.stats.errors += res.stats.errors || 0;
                }

                if (res && typeof res.nextOffset === "number") {
                    self.state.offset = res.nextOffset;
                }

                if (res && res.resetProducts && res.resetProducts.length) {
                    self.state.resetProducts = res.resetProducts;
                }

                if (res && res.errors && res.errors.length) {
                    for (var i = 0; i < res.errors.length; i++) {
                        self._appendLog(res.errors[i], "error");
                    }
                }

                if (res && res.warnings && res.warnings.length) {
                    for (var j = 0; j < res.warnings.length; j++) {
                        self._appendLog(res.warnings[j], "warn");
                    }
                }

                self._render();

                if (res && res.done) {
                    self.state.running = false;
                    var hasErrors = false;
                    if (res && res.stats && (res.stats.errors || 0) > 0) {
                        hasErrors = true;
                    }
                    if (res && res.errors && res.errors.length) {
                        hasErrors = true;
                    }

                    if (hasErrors) {
                        self._setStatus("Errore");
                        self._setProgressMode("stopped");
                    } else {
                        self._setStatus("Completato");
                        self._setProgressMode("done");
                        self._appendLog("Import completato.");
                    }

                    self._enableClose(true);

                    if (typeof self.options.onDone === "function") {
                        self.options.onDone({
                            stats: self.state.stats,
                            chunks: self.state.chunks,
                            offset: self.state.offset,
                            resetProducts: self.state.resetProducts,
                            hasErrors: hasErrors,
                        });
                    }
                    return;
                }

                setTimeout(function () {
                    self._tick();
                }, self.options.delayMs || 150);
            })
            .catch(function (err) {
                if (self.state.stopped) {
                    self._tick();
                    return;
                }

                self.state.running = false;
                self._setStatus("Errore");
                self._setProgressMode("stopped");
                self._enableClose(true);

                var msg = err && err.message ? err.message : "Errore inatteso durante la richiesta.";
                self._appendLog(msg, "error");

                if (typeof self.options.onError === "function") {
                    self.options.onError(err);
                }
            });
    };

    ImportManager.prototype._requestChunk = function (offset, limit, resetProducts) {
        var self = this;
        var url = this.options.url;
        if (!url) {
            return Promise.reject(new Error("ImportManager: manca options.url"));
        }

        var payload = {
            ajax: 1,
            action: this.options.action || "mpMassImportProductsImportChunk",
            token: this.options.token || undefined,
            template_name: this.options.templateName || undefined,
            file_path: this.options.filePath || undefined,
            file_type: this.options.fileType || undefined,
            offset: offset,
            limit: limit,
            defaults: this.options.defaults || {},
            price_settings: this.options.priceSettings || {},
            resetProducts: resetProducts || [],
        };

        if (typeof this.options.buildPayload === "function") {
            payload = this.options.buildPayload(payload) || payload;
        }

        if (window.$ && $.ajax) {
            return new Promise(function (resolve, reject) {
                self._xhr = $.ajax({
                    url: url,
                    method: "POST",
                    dataType: "json",
                    data: payload,
                    timeout: self.options.timeoutMs || 600000,
                })
                    .done(function (data) {
                        self._xhr = null;
                        resolve(data);
                    })
                    .fail(function (jqXHR, textStatus, errorThrown) {
                        self._xhr = null;
                        if (textStatus === "abort") {
                            reject(new Error("Richiesta interrotta"));
                            return;
                        }
                        var msg = errorThrown || textStatus || "Errore AJAX";
                        reject(new Error(msg));
                    });
            });
        }

        return Promise.reject(new Error("ImportManager: jQuery non disponibile"));
    };

    ImportManager.prototype._ensureUi = function () {
        if (this._els) {
            return;
        }

        this._injectStyles();

        var root = document.createElement("div");
        root.className = "mpim-root";
        root.setAttribute("aria-hidden", "true");
        root.innerHTML = `
            <div class="mpim-backdrop"></div>
            <div class="mpim-dialog" role="dialog" aria-modal="true" aria-label="Import prodotti">
                <div class="mpim-header">
                    <div class="mpim-title">Importazione prodotti</div>
                    <div class="mpim-status js-mpim-status">—</div>
                </div>
                <div class="mpim-body">
                    <div class="mpim-progress">
                        <div class="mpim-progressbar">
                            <div class="mpim-progressbar__fill js-mpim-fill"></div>
                        </div>
                        <div class="mpim-progressmeta">
                            <div class="mpim-meta-item">
                                <span class="mpim-meta-label">Chunk</span>
                                <span class="mpim-meta-value js-mpim-chunks">0</span>
                            </div>
                            <div class="mpim-meta-item">
                                <span class="mpim-meta-label">Processati</span>
                                <span class="mpim-meta-value js-mpim-processed">0</span>
                            </div>
                            <div class="mpim-meta-item">
                                <span class="mpim-meta-label">Importati</span>
                                <span class="mpim-meta-value js-mpim-imported">0</span>
                            </div>
                            <div class="mpim-meta-item">
                                <span class="mpim-meta-label">Saltati</span>
                                <span class="mpim-meta-value js-mpim-skipped">0</span>
                            </div>
                            <div class="mpim-meta-item">
                                <span class="mpim-meta-label">Errori</span>
                                <span class="mpim-meta-value js-mpim-errors">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="mpim-log" role="log" aria-live="polite" aria-relevant="additions">
                        <div class="mpim-log-inner js-mpim-log"></div>
                    </div>
                </div>
                <div class="mpim-footer">
                    <button type="button" class="mpim-btn mpim-btn--ghost js-mpim-close" disabled>Chiudi</button>
                    <button type="button" class="mpim-btn mpim-btn--danger js-mpim-stop">Interrompi</button>
                </div>
            </div>
        `;

        document.body.appendChild(root);

        var stopBtn = root.querySelector(".js-mpim-stop");
        var closeBtn = root.querySelector(".js-mpim-close");
        var backdrop = root.querySelector(".mpim-backdrop");

        var self = this;
        stopBtn.addEventListener("click", function () {
            self.stop();
        });

        closeBtn.addEventListener("click", function () {
            self.close();
        });

        backdrop.addEventListener("click", function () {
            if (!self.state.running) {
                self.close();
            }
        });

        this._els = {
            root: root,
            status: root.querySelector(".js-mpim-status"),
            fill: root.querySelector(".js-mpim-fill"),
            chunks: root.querySelector(".js-mpim-chunks"),
            processed: root.querySelector(".js-mpim-processed"),
            imported: root.querySelector(".js-mpim-imported"),
            skipped: root.querySelector(".js-mpim-skipped"),
            errors: root.querySelector(".js-mpim-errors"),
            log: root.querySelector(".js-mpim-log"),
            closeBtn: closeBtn,
            stopBtn: stopBtn,
        };
    };

    ImportManager.prototype._injectStyles = function () {
        if (this._styleInjected) {
            return;
        }
        this._styleInjected = true;

        var style = document.createElement("style");
        style.setAttribute("type", "text/css");
        var css = `
            .mpim-root {
                position: fixed;
                inset: 0;
                z-index: 100000;
                display: none;
                font-family: system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
            }
            
            .mpim-root.mpim-open {
                display: block;
            }
            
            .mpim-root .mpim-backdrop {
                position: absolute;
                inset: 0;
                background: rgba(0, 0, 0, .55);
                backdrop-filter: saturate(120%) blur(2px);
            }
            
            .mpim-root .mpim-dialog {
                position: relative;
                max-width: 820px;
                width: calc(100% - 40px);
                margin: 60px auto;
                background: #fff;
                border-radius: 14px;
                box-shadow: 0 30px 80px rgba(0, 0, 0, .35);
                overflow: hidden;
            }
            
            .mpim-root .mpim-header {
                display: flex;
                align-items: flex-end;
                justify-content: space-between;
                padding: 18px 20px;
                border-bottom: 1px solid rgba(0, 0, 0, .08);
            }
            
            .mpim-root .mpim-title {
                font-size: 18px;
                font-weight: 700;
                color: #111;
            }
            
            .mpim-root .mpim-status {
                font-size: 13px;
                color: #666;
            }
            
            .mpim-root .mpim-body {
                padding: 16px 20px;
            }
            
            .mpim-root .mpim-progress {
                margin-bottom: 12px;
            }
            
            .mpim-root .mpim-progressbar {
                height: 10px;
                background: rgba(0, 0, 0, .08);
                border-radius: 999px;
                overflow: hidden;
                position: relative;
            }
            
            .mpim-root .mpim-progressbar__fill {
                height: 100%;
                width: 0%;
                background: linear-gradient(90deg, #6d5dfc, #4cc9f0);
                border-radius: 999px;
                transition: width .25s ease;
            }
            
            .mpim-root .mpim-progressbar__fill.mpim-indeterminate {
                width: 35%;
                position: absolute;
                left: -35%;
                animation: mpim-indeterminate 1.1s infinite ease-in-out;
            }
            
            @keyframes mpim-indeterminate {
                0% {
                    left: -35%;
                }
                100% {
                    left: 100%;
                }
            }
            
            .mpim-root .mpim-progressmeta {
                display: grid;
                grid-template-columns: repeat(5, minmax(0, 1fr));
                gap: 10px;
                margin-top: 10px;
            }
            
            .mpim-root .mpim-meta-item {
                background: rgba(0, 0, 0, .03);
                border: 1px solid rgba(0, 0, 0, .06);
                border-radius: 10px;
                padding: 10px 10px;
            }
            
            .mpim-root .mpim-meta-label {
                display: block;
                font-size: 11px;
                color: #666;
                margin-bottom: 2px;
            }
            
            .mpim-root .mpim-meta-value {
                display: block;
                font-size: 14px;
                font-weight: 700;
                color: #111;
            }
            
            .mpim-root .mpim-log {
                height: 260px;
                overflow: auto;
                border: 1px solid rgba(0, 0, 0, .08);
                border-radius: 10px;
                background: #0b1020;
            }
            
            .mpim-root .mpim-log-inner {
                padding: 10px;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
                font-size: 12px;
                line-height: 1.45;
                color: #cdd6f4;
            }
            
            .mpim-root .mpim-log-line {
                padding: 2px 0;
                white-space: pre-wrap;
                word-break: break-word;
            }
            
            .mpim-root .mpim-log-line.mpim-error {
                color: #b42318;
                background: rgba(180, 35, 24, .06);
                border-left: 3px solid #b42318;
                padding-left: 10px;
            }
            
            .mpim-root .mpim-log-line.mpim-warn {
                color: #b54708;
                background: rgba(181, 71, 8, .06);
                border-left: 3px solid #b54708;
                padding-left: 10px;
            }
            
            .mpim-root .mpim-footer {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                padding: 14px 18px;
                border-top: 1px solid #e7e7e7;
            }
            
            .mpim-root .mpim-btn {
                appearance: none;
                border: 0;
                border-radius: 10px;
                padding: 10px 14px;
                font-weight: 600;
                cursor: pointer;
            }
            
            .mpim-root .mpim-btn[disabled] {
                opacity: .6;
                cursor: not-allowed;
            }
            
            .mpim-root .mpim-btn--ghost {
                background: #f2f4f7;
                color: #111;
            }
            
            .mpim-root .mpim-btn--danger {
                background: #d92d20;
                color: #fff;
            }
            
            .mpim-root .mpim-btn--danger:hover {
                filter: brightness(.96);
            }
        `;

        style.textContent = css;
        document.head.appendChild(style);
    };

    ImportManager.prototype._open = function () {
        this._els.root.classList.add("mpim-open");
        this._els.root.setAttribute("aria-hidden", "false");
        this._enableClose(false);
    };

    ImportManager.prototype._close = function () {
        this._els.root.classList.remove("mpim-open");
        this._els.root.setAttribute("aria-hidden", "true");
    };

    ImportManager.prototype._enableClose = function (enabled) {
        if (!this._els) {
            return;
        }
        this._els.closeBtn.disabled = !enabled;
    };

    ImportManager.prototype._setStatus = function (text) {
        if (this._els && this._els.status) {
            this._els.status.textContent = text;
        }
    };

    ImportManager.prototype._setProgressMode = function (mode) {
        if (!this._els || !this._els.fill) {
            return;
        }
        this._els.fill.classList.remove("mpim-indeterminate");
        if (mode === "indeterminate") {
            this._els.fill.classList.add("mpim-indeterminate");
            return;
        }
        if (mode === "done") {
            this._els.fill.style.width = "100%";
            return;
        }
        if (mode === "stopped") {
            this._els.fill.style.width = "0%";
        }
    };

    ImportManager.prototype._render = function () {
        if (!this._els) {
            return;
        }
        this._els.chunks.textContent = String(this.state.chunks);
        this._els.processed.textContent = String(this.state.stats.processed);
        this._els.imported.textContent = String(this.state.stats.imported);
        this._els.skipped.textContent = String(this.state.stats.skipped);
        this._els.errors.textContent = String(this.state.stats.errors);
        this._els.stopBtn.disabled = !this.state.running;
    };

    ImportManager.prototype._appendLog = function (message, level) {
        if (!this._els || !this._els.log) {
            return;
        }
        var line = document.createElement("div");
        line.className = "mpim-log-line" + (level === "error" ? " mpim-error" : "") + (level === "warn" ? " mpim-warn" : "");
        var now = new Date();
        var ts = now.toLocaleTimeString();
        line.textContent = "[" + ts + "] " + String(message);
        this._els.log.appendChild(line);
        this._els.log.scrollTop = this._els.log.scrollHeight;
    };

    window.MpMassImportProductsImportManager = ImportManager;
})(window);
