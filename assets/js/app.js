document.addEventListener("DOMContentLoaded", function()
{
    document.querySelectorAll('.add-new-attribute-value').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            // A category added from here must file under this form's kind.
            const requestData = {
                requestType: 'load-form',
                buttonId: this.id,
                itemType: this.dataset.itemType || 'part'
            };

            sendAjaxRequest("inc/ajax.php", requestData, 
                (data) => {
                    showModalWithForm(data.formHtml, data.selectId);
                },
                (error) => {
                    console.error("Error loading form:", error);
                    window.alert("That form could not be loaded. Please try again.");
                }
            );
        });
    });

    document.querySelectorAll("select").forEach(select => {
        createSearchableSelect(select);
    });

    document.querySelectorAll("table.sortable").forEach(table => {
        makeTableSortable(table);
    });

    document.querySelectorAll("input.select-all").forEach(box => {
        makeSelectAll(box);
    });

    document.querySelectorAll(".filter-bar[data-collapsible]").forEach(bar => {
        makeCollapsibleFilters(bar);
    });

    const bulkBar = document.getElementById("bulk-items");

    if (bulkBar) {
        makeBulkBar(bulkBar);
    }

    document.querySelectorAll('a[href="#print"]').forEach(link => {
        link.addEventListener("click", event => {
            event.preventDefault();
            window.print();
        });
    });

    // input for text fields, change for dropdowns. Delegated so controls built
    // later by the searchable select are covered.
    document.addEventListener("input", clearFieldError);
    document.addEventListener("change", clearFieldError);

    document.querySelectorAll("input[type=file][data-max-bytes]").forEach(input => {
        input.addEventListener("change", () => checkUploadSize(input));
    });

    document.addEventListener("submit", preventDoubleSubmit);

    document.addEventListener("input", markFormChanged);
    document.addEventListener("change", markFormChanged);
    window.addEventListener("beforeunload", warnOfUnsavedChanges);

    // Coming back with the browser's Back button can restore a page mid-submit.
    window.addEventListener("pageshow", () => {
        document.querySelectorAll("form[data-submitting]").forEach(form => {
            delete form.dataset.submitting;
        });
    });
});

/**
 * Refuses files over the server's limits before they are sent. One over
 * post_max_size would otherwise arrive as an empty form and lose everything.
 */
function checkUploadSize(input)
{
    const files = Array.from(input.files);
    const perFile = Number(input.dataset.maxBytes) || 0;
    const total = Number(input.dataset.maxTotal) || 0;
    const megabytes = bytes => (bytes / 1048576).toFixed(bytes < 10485760 ? 1 : 0) + "MB";

    let message = "";
    const tooBig = files.find(file => perFile && file.size > perFile);

    if (tooBig) {
        message = `"${tooBig.name}" is larger than the ${megabytes(perFile)} allowed.`;
    } else if (total && files.reduce((sum, file) => sum + file.size, 0) > total) {
        message = `Those files come to more than the ${megabytes(total)} that can be sent at once. Upload them in smaller batches.`;
    }

    input.setCustomValidity(message);

    if (message) {
        input.reportValidity();
    }
}

/**
 * Notes a POST form whose fields have been used. Left out: the bulk bar, whose
 * ticks are a selection rather than something to save, and the add-new modal,
 * which saves by itself. .form is used, as the table's tick boxes join the bar
 * by their form attribute.
 */
function markFormChanged(event)
{
    const form = event.target.form;

    // Typing in a dropdown's search box changes nothing.
    if (!form || event.target.classList.contains("searchable-select__search")
        || form.id === "bulk-items" || form.closest("#custom-modal")
        || (form.getAttribute("method") || "").toLowerCase() !== "post") {
        return;
    }

    form.dataset.changed = "true";
}

/** Asks before leaving with changes unsaved, unless a form is being sent. */
function warnOfUnsavedChanges(event)
{
    if (document.querySelector("form[data-submitting]") || !document.querySelector("form[data-changed]")) {
        return;
    }

    event.preventDefault();
    event.returnValue = "";
}

/**
 * Ignores a POST form's second submit while the first is on its way, so a
 * double-click cannot save twice. The button is left enabled, as a disabled
 * button's name is not sent and the pages look for it. Runs after the forms'
 * own handlers, so a cancelled confirm does not count.
 */
function preventDoubleSubmit(event)
{
    const form = event.target;

    if (event.defaultPrevented || (form.getAttribute("method") || "").toLowerCase() !== "post" || form.dataset.resubmit === "true") {
        return;
    }

    if (form.dataset.submitting) {
        event.preventDefault();
        return;
    }

    form.dataset.submitting = "true";
}

/** Clear a field's error once its control is used; the server re-checks on save. */
function clearFieldError(event)
{
    // Typing in a dropdown's search box is not a choice.
    if (event.target.classList.contains("searchable-select__search")) {
        return;
    }

    // The bulk bar's controls are not in form rows, so mark their own wrapper.
    const row = event.target.closest(".form-row.field-invalid, .bulk-bar .field-invalid");

    if (!row) {
        return;
    }

    row.classList.remove("field-invalid");

    const message = row.querySelector(".field-error");

    if (message) {
        message.remove();
    }

    row.querySelectorAll("[aria-invalid]").forEach(control => {
        control.removeAttribute("aria-invalid");
        control.removeAttribute("aria-describedby");
    });

    clearSummaryEntry(row.dataset.field);
}

/** Drop a field's line from the form summary, and the summary once empty. */
function clearSummaryEntry(field)
{
    if (!field) {
        return;
    }

    document.querySelectorAll(".form-message").forEach(summary => {
        // A single-message summary goes as a whole.
        if (summary.dataset.field === field) {
            summary.remove();
            return;
        }

        const entry = summary.querySelector('li[data-field="' + field + '"]');

        if (entry) {
            entry.remove();
        }

        const list = summary.querySelector("ul");

        if (list && !list.querySelector("li")) {
            summary.remove();
        }
    });
}

/** A heading tick box that ticks or clears every row's box. */
function makeSelectAll(box) {
    const table = box.closest("table");
    const rows = () => Array.from(table.querySelectorAll('td input[type="checkbox"]'));

    const reflect = () => {
        const ticked = rows().filter(row => row.checked).length;

        box.checked = ticked > 0 && ticked === rows().length;
        box.indeterminate = ticked > 0 && ticked < rows().length;
    };

    box.addEventListener("change", () => {
        rows().forEach(row => {
            row.checked = box.checked;
        });
    });

    table.addEventListener("change", event => {
        if (event.target !== box) {
            reflect();
        }
    });

    // Rows come back ticked after a rejected submit.
    reflect();
}

/** On mobile, tuck the dropdowns behind a toggle so only the search shows. */
function makeCollapsibleFilters(bar) {
    const toggle = bar.querySelector(".filter-toggle");

    bar.classList.add("is-collapsible");
    toggle.hidden = false;

    toggle.addEventListener("click", () => {
        const expanded = bar.classList.toggle("is-expanded");
        toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    });
}

/**
 * The bulk actions bar. Shown once a row is ticked; offers "select all matching"
 * when the whole page is ticked and there are more pages.
 */
function makeBulkBar(form) {
    const rows = Array.from(document.querySelectorAll('input[type="checkbox"][form="' + form.id + '"]'));
    const everything = form.querySelector('input[name="bulk_all"]');
    const summary = form.querySelector(".bulk-bar-summary");
    const scope = form.querySelector(".bulk-bar-scope");
    const action = form.querySelector('select[name="bulk_action"]');
    const submit = form.querySelector('input[type="submit"]');
    const total = parseInt(form.dataset.total, 10) || 0;
    const noun = form.dataset.noun;

    const update = () => {
        const ticked = rows.filter(row => row.checked).length;

        if (ticked < rows.length) {
            everything.value = "";
        }

        const all = everything.value === "1";

        summary.textContent = all
            ? "All " + total + " matching " + noun + " selected"
            : ticked + " selected";

        scope.hidden = !all && !(ticked === rows.length && total > rows.length);
        scope.textContent = all ? "Just this page" : "Select all " + total + " matching";

        form.hidden = ticked === 0 && !all;
    };

    const field = () => form.querySelector('.bulk-bar-field[data-action="' + action.value + '"]');

    const showField = () => {
        form.querySelectorAll(".bulk-bar-field").forEach(span => {
            span.hidden = span.dataset.action !== action.value;
        });

        submit.classList.toggle("delete", action.value === "delete");
    };

    // Fires change so the heading box updates too.
    const tickRows = checked => {
        rows.forEach(row => {
            row.checked = checked;
        });

        if (rows.length) {
            rows[0].dispatchEvent(new Event("change", { bubbles: true }));
        }
    };

    scope.addEventListener("click", () => {
        if (everything.value === "1") {
            everything.value = "";
            update();
            return;
        }

        tickRows(true);
        everything.value = "1";
        update();
    });

    action.addEventListener("change", showField);

    // Confirm deletes, and any change to everything matching.
    form.addEventListener("submit", event => {
        // These download or open a page while this one stays, so the bar must
        // stay usable after them.
        form.dataset.resubmit = (action.value === "labels" || action.value === "export") ? "true" : "";

        if (!action.value || action.value === "labels" || action.value === "export") {
            return;
        }

        const all = everything.value === "1";
        const which = all
            ? "all " + total + " matching " + noun
            : rows.filter(row => row.checked).length + " selected " + noun;

        let question;

        if (action.value === "delete") {
            // As on the single delete, say what goes with them.
            question = "Delete " + which + "? Their places on project assemblies (including what was"
                + " installed), sign-out history, stock history, photos and documents go too."
                + " This cannot be undone.";
        } else {
            const choice = field() && field().querySelector("select");

            if (!all || !choice || !choice.value) {
                return;
            }

            const label = action.options[action.selectedIndex].text.toLowerCase();

            question = label.charAt(0).toUpperCase() + label.slice(1) + " \u201c"
                + choice.options[choice.selectedIndex].text + "\u201d for " + which + "?";
        }

        if (!window.confirm(question)) {
            event.preventDefault();
        }
    });

    document.addEventListener("change", event => {
        if (rows.includes(event.target) || event.target.classList.contains("select-all")) {
            update();
        }
    });

    showField();
    update();
}

/** Click a heading to sort by that column; the first row holds the headings. */
function makeTableSortable(table) {
    const headerRow = table.rows[0];

    if (!headerRow || table.rows.length < 3) {
        return;
    }

    Array.from(headerRow.cells).forEach((cell, index) => {
        if (!cell.textContent.trim()) {
            return;
        }

        cell.classList.add("is-sortable");
        cell.setAttribute("role", "button");
        cell.setAttribute("tabindex", "0");

        const sort = () => sortTableByColumn(table, index, cell);

        cell.addEventListener("click", sort);
        cell.addEventListener("keydown", event => {
            if (event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                sort();
            }
        });
    });
}

function sortTableByColumn(table, index, headerCell) {
    const headerRow = table.rows[0];
    const rows = Array.from(table.rows).slice(1);
    const ascending = headerCell.dataset.sortDirection !== "asc";

    // Use a control's value, as an <input> has no text content.
    const cellValue = row => {
        const cell = row.cells[index];

        if (!cell) {
            return "";
        }

        const field = cell.querySelector("input:not([type=submit]):not([type=hidden]), select, textarea");

        return (field ? field.value : cell.textContent).trim();
    };

    const asNumber = value => {
        const cleaned = value.replace(/[^0-9.\-]/g, "");
        return cleaned === "" || cleaned === "-" ? NaN : parseFloat(cleaned);
    };

    const numeric = rows.every(row => {
        const value = cellValue(row);
        return value === "" || !isNaN(asNumber(value));
    });

    // formatDate() writes "5 Mar 2025" or "5 Mar 2025 14:30", maybe followed
    // by a note. Read as a number, that would sort by day of the month.
    const months = ["jan", "feb", "mar", "apr", "may", "jun", "jul", "aug", "sep", "oct", "nov", "dec"];

    const asDate = value => {
        const match = value.match(/^(\d{1,2}) ([A-Za-z]{3}) (\d{4})(?: (\d{2}):(\d{2}))?/);
        const month = match ? months.indexOf(match[2].toLowerCase()) : -1;

        return month === -1
            ? NaN
            : new Date(+match[3], month, +match[1], +(match[4] || 0), +(match[5] || 0)).getTime();
    };

    // Cells without a date, such as "Not returned", hold no digits.
    const dated = rows.some(row => !isNaN(asDate(cellValue(row))))
        && rows.every(row => {
            const value = cellValue(row);
            return !isNaN(asDate(value)) || !/\d/.test(value);
        });

    rows.sort((a, b) => {
        const first = cellValue(a);
        const second = cellValue(b);

        if (dated) {
            // Undated cells sort before every date, as blank numbers do.
            const x = isNaN(asDate(first)) ? -Infinity : asDate(first);
            const y = isNaN(asDate(second)) ? -Infinity : asDate(second);

            if (x === y) {
                return 0;
            }

            return ascending ? (x < y ? -1 : 1) : (x < y ? 1 : -1);
        }

        if (numeric) {
            const x = isNaN(asNumber(first)) ? 0 : asNumber(first);
            const y = isNaN(asNumber(second)) ? 0 : asNumber(second);
            return ascending ? x - y : y - x;
        }

        return ascending
            ? first.localeCompare(second, undefined, { numeric: true, sensitivity: "base" })
            : second.localeCompare(first, undefined, { numeric: true, sensitivity: "base" });
    });

    rows.forEach(row => table.tBodies[0].appendChild(row));

    Array.from(headerRow.cells).forEach(cell => {
        delete cell.dataset.sortDirection;
        cell.classList.remove("is-sorted-asc", "is-sorted-desc");
    });

    headerCell.dataset.sortDirection = ascending ? "asc" : "desc";
    headerCell.classList.add(ascending ? "is-sorted-asc" : "is-sorted-desc");
}

function sendAjaxRequest(url, data, onSuccess = () => {}, onError = (err) => console.error(err))
{
    fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            return response.json().then(errData => {
                throw new Error(`HTTP ${response.status}: ${JSON.stringify(errData)}`);
            });
        }
        return response.json();
    })
    .then(onSuccess)
    .catch(error => {
        if (onError) onError(error);
    });
}

function showModalWithForm(formHtml, dropdownId)
{
    let modal = document.getElementById("custom-modal");
    
    if (!modal) {
        modal = document.createElement("div");
        modal.id = "custom-modal";
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <button type="button" class="close-modal" aria-label="Close">&times;</button>
                <div id="modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);

        modal.setAttribute("role", "dialog");
        modal.setAttribute("aria-modal", "true");

        modal.querySelector(".close-modal").addEventListener("click", closeModal);
        modal.querySelector(".modal-overlay").addEventListener("click", closeModal);

        modal.addEventListener("keydown", event => {
            // An open searchable select handles its own Escape first.
            if (event.key === "Escape" && !event.defaultPrevented) {
                closeModal();
            }
        });
    }

    // Focus goes back to the "+" that opened it.
    modal.returnFocus = document.activeElement;

    document.getElementById("modal-body").innerHTML = formHtml;
    modal.style.display = "flex";

    attachFormSubmitHandler(dropdownId);

    const first = modal.querySelector("#modal-body input:not([type=hidden]), #modal-body select");

    if (first) {
        first.focus();
    }
}

function attachFormSubmitHandler(dropdownId)
{
    const form = document.querySelector("#modal-body form");
    if (!form) return;

    form.addEventListener("submit", function(e) {
        e.preventDefault();

        // Sent by fetch, so preventDoubleSubmit() never sees it.
        if (form.dataset.submitting) {
            return;
        }

        form.dataset.submitting = "true";

        const done = () => {
            delete form.dataset.submitting;
        };

        const formData = new FormData(form);
        let requestData = {
            requestType: "submit-form",
            formId: dropdownId,
            itemType: itemTypeOf(dropdownId),
            formData: Object.fromEntries(formData.entries())
        };
        const errorContainer = document.getElementById("modal-error-message");
        if (errorContainer) errorContainer.innerHTML = "";

        sendAjaxRequest("inc/ajax.php", requestData, 
            (data) => {
                done();

                if (data.success) {
                    closeModal();
                    refreshDropdown(dropdownId, data.newId);
                } else {
                    showModalError(data.error || "An unknown error occurred.");
                }
            },
            (error) => {
                done();
                console.error("Error submitting form:", error);
                showModalError("A server error occurred. Please try again.");
            }
        );
    });
}

function closeModal()
{
    let modal = document.getElementById("custom-modal");
    if (modal) {
        modal.style.display = "none";

        if (modal.returnFocus && document.contains(modal.returnFocus)) {
            modal.returnFocus.focus();
        }
    }
}

function showModalError(message)
{
    let errorContainer = document.getElementById("modal-error-message");

    if (!errorContainer) {
        errorContainer = document.createElement("div");
        errorContainer.id = "modal-error-message";
        errorContainer.style.color = "red";
        errorContainer.style.marginBottom = "10px";
        document.getElementById("modal-body").prepend(errorContainer);
    }

    errorContainer.innerHTML = message;
}

/** Whether the item form a dropdown belongs to is editing a part or a tool. */
function itemTypeOf(dropdownId)
{
    const select = document.getElementById(dropdownId);

    return (select && select.dataset.itemType) || 'part';
}

function refreshDropdown(dropdownId, newId)
{
    const select = document.getElementById(dropdownId);
    if (!select) return;

    // Keep what is already chosen; a multi-select would otherwise lose it.
    const selected = Array.from(select.selectedOptions).map(option => option.value);

    let requestData = {
        requestType: 'get-downdown-options',
        dropdownId: dropdownId,
        itemType: itemTypeOf(dropdownId),
        newId: newId,
        selected: selected,
        multiple: select.multiple
    };

    // Put back on failure, or the form would submit "Loading..." or nothing.
    const previous = Array.from(select.childNodes);

    select.innerHTML = "<option>Loading...</option>";

    sendAjaxRequest("inc/ajax.php", requestData,
        (data) => {
            select.innerHTML = data.optionsHtml;
            select.dispatchEvent(new Event("change", { bubbles: true }));
        },
        (error) => {
            console.error("Error refreshing dropdown:", error);
            select.replaceChildren(...previous);
            select.dispatchEvent(new Event("change", { bubbles: true }));
        }
    );
}

function createSearchableSelect(select) {
    if (select.dataset.searchableInitialized) {
        return;
    }

    select.dataset.searchableInitialized = "true";

    const multiple = select.multiple;
    const wrapper = document.createElement("div");

    wrapper.className = "searchable-select";

    select.parentNode.insertBefore(wrapper, select);
    wrapper.appendChild(select);

    const button = document.createElement("button");

    button.type = "button";
    button.className = "searchable-select__button";
    button.setAttribute("aria-haspopup", "listbox");
    button.setAttribute("aria-expanded", "false");

    const value = document.createElement("span");
    value.className = "searchable-select__value";

    button.appendChild(value);

    const menu = document.createElement("div");
    menu.className = "searchable-select__menu";

    const search = document.createElement("input");
    search.type = "search";
    search.className = "searchable-select__search";
    search.placeholder = "Search...";
    search.autocomplete = "off";

    const optionsContainer = document.createElement("div");
    optionsContainer.className = "searchable-select__options";
    optionsContainer.setAttribute("role", "listbox");

    if (multiple) {
        optionsContainer.setAttribute("aria-multiselectable", "true");
    }

    menu.appendChild(search);
    menu.appendChild(optionsContainer);

    wrapper.appendChild(button);
    wrapper.appendChild(menu);

    let highlightedIndex = -1;

    // Each <optgroup>'s heading and the index of its first option.
    let groupHeadings = [];

    // Set while this widget changes the select itself, so its own change
    // event does not rebuild the list under the search.
    let selecting = false;

    function getOptions() {
        return Array.from(optionsContainer.querySelectorAll(
            ".searchable-select__option:not([hidden])"
        ));
    }

    function updateValue() {
        const selected = Array.from(select.options)
            .filter(option => option.selected);

        if (selected.length === 0) {
            value.textContent =
                select.dataset.placeholder || "Select...";
            value.classList.add("searchable-select__placeholder");
            return;
        }

        value.classList.remove("searchable-select__placeholder");

        if (!multiple) {
            value.textContent = selected[0].textContent;
            return;
        }

        if (selected.length === 1) {
            value.textContent = selected[0].textContent;
            return;
        }

        const count = document.createElement("span");
        count.className = "searchable-select__count";
        count.textContent = selected.length;

        value.replaceChildren();

        const text = document.createTextNode(
            `${selected.length} selected`
        );

        value.appendChild(text);
        value.appendChild(count);
    }

    function createOptions() {
        optionsContainer.innerHTML = "";
        groupHeadings = [];

        let group = null;

        Array.from(select.options).forEach((option, index) => {
            const parent = option.parentElement;
            const groupLabel = parent.tagName === "OPTGROUP" ? parent.label : "";

            if (groupLabel && parent !== group) {
                const heading = document.createElement("div");

                heading.className = "searchable-select__group";
                heading.setAttribute("role", "presentation");
                heading.textContent = groupLabel;

                optionsContainer.appendChild(heading);
                groupHeadings.push({ heading, firstIndex: index });
            }

            group = groupLabel ? parent : null;

            const item = document.createElement("div");

            item.className = "searchable-select__option";
            item.setAttribute("role", "option");
            item.dataset.index = index;
            item.textContent = option.textContent;

            // Search results lose the headings, so each says which it is under.
            if (groupLabel) {
                const note = document.createElement("small");

                note.className = "searchable-select__group-note";
                note.textContent = groupLabel;

                item.appendChild(note);
            }

            if (option.disabled) {
                item.setAttribute("aria-disabled", "true");
            }

            if (option.selected) {
                item.classList.add("is-selected");
                item.setAttribute("aria-selected", "true");
            } else {
                item.setAttribute("aria-selected", "false");
            }

            if (multiple) {
                const checkbox = document.createElement("input");

                checkbox.type = "checkbox";
                checkbox.tabIndex = -1;
                checkbox.checked = option.selected;
                checkbox.disabled = option.disabled;
                checkbox.setAttribute("aria-hidden", "true");

                item.prepend(checkbox);
            }

            item.addEventListener("mousedown", event => {
                event.preventDefault();
            });

            item.addEventListener("click", () => {
                if (option.disabled) {
                    return;
                }

                selectOption(index);

                if (!multiple) {
                    close();
                }
            });

            optionsContainer.appendChild(item);
        });

        updateValue();
    }

    function selectOption(index) {
        const option = select.options[index];

        if (!option || option.disabled) {
            return;
        }

        if (multiple) {
            option.selected = !option.selected;
        } else {
            select.selectedIndex = index;
        }

        selecting = true;

        select.dispatchEvent(
            new Event("change", { bubbles: true })
        );

        selecting = false;

        syncSelection();
    }

    /** Marks what is selected without rebuilding, so the search and highlight stay. */
    function syncSelection() {
        optionsContainer.querySelectorAll(".searchable-select__option").forEach(item => {
            const option = select.options[Number(item.dataset.index)];
            const selected = Boolean(option && option.selected);
            const checkbox = item.querySelector("input");

            item.classList.toggle("is-selected", selected);
            item.setAttribute("aria-selected", selected ? "true" : "false");

            if (checkbox) {
                checkbox.checked = selected;
            }
        });

        updateValue();
    }

    function open() {
        if (wrapper.classList.contains("is-open")) {
            return;
        }

        wrapper.classList.add("is-open");
        button.setAttribute("aria-expanded", "true");

        search.value = "";
        filterOptions();

        requestAnimationFrame(() => {
            search.focus();
        });
    }

    function close() {
        wrapper.classList.remove("is-open");
        button.setAttribute("aria-expanded", "false");

        highlightedIndex = -1;
        search.value = "";
        filterOptions();
    }

    function filterOptions() {
        const query = search.value.trim().toLowerCase();

        const items = Array.from(
            optionsContainer.querySelectorAll(
                ".searchable-select__option"
            )
        );

        const results = [];

        items.forEach(item => {
            const index = Number(item.dataset.index);
            const option = select.options[index];

            const text = option.textContent.trim().toLowerCase();

            let score = 0;

            if (!query) {
                score = 0;
            } else if (text === query) {
                score = 0;
            } else if (text.startsWith(query)) {
                score = 1;
            } else if (text.includes(query)) {
                score = 2;
            } else {
                score = 999;
            }

            if (score < 999) {
                results.push({
                    item,
                    index,
                    score
                });
            }
        });

        results.sort((a, b) => {
            if (a.score !== b.score) {
                return a.score - b.score;
            }

            return a.index - b.index;
        });

        results.forEach(result => {
            result.item.hidden = false;
            optionsContainer.appendChild(result.item);
        });

        // Ranked results are not in group order, so headings show only for
        // the full list, each back above its first option.
        optionsContainer.classList.toggle("is-filtered", Boolean(query));

        groupHeadings.forEach(({ heading, firstIndex }) => {
            heading.hidden = Boolean(query);

            if (!query) {
                const first = optionsContainer.querySelector(
                    `.searchable-select__option[data-index="${firstIndex}"]`
                );

                optionsContainer.insertBefore(heading, first);
            }
        });

        items.forEach(item => {
            if (!results.some(result => result.item === item)) {
                item.hidden = true;
            }
        });

        let noResults =
            optionsContainer.querySelector(
                ".searchable-select__no-results"
            );

        if (results.length === 0) {
            if (!noResults) {
                noResults = document.createElement("div");
                noResults.className =
                    "searchable-select__no-results";
                noResults.textContent = "No results found.";
                optionsContainer.appendChild(noResults);
            }
        } else if (noResults) {
            noResults.remove();
        }

        highlightedIndex = -1;
        highlightOption();
    }

    function highlightOption() {
        const items = getOptions();

        items.forEach(item => {
            item.classList.remove("is-highlighted");
        });

        if (
            highlightedIndex >= 0 &&
            highlightedIndex < items.length
        ) {
            const item = items[highlightedIndex];

            item.classList.add("is-highlighted");

            item.scrollIntoView({
                block: "nearest"
            });
        }
    }

    function moveHighlight(direction) {
        const items = getOptions();

        if (!items.length) {
            return;
        }

        highlightedIndex += direction;

        if (highlightedIndex < 0) {
            highlightedIndex = items.length - 1;
        }

        if (highlightedIndex >= items.length) {
            highlightedIndex = 0;
        }

        highlightOption();
    }

    button.addEventListener("click", () => {
        if (wrapper.classList.contains("is-open")) {
            close();
        } else {
            open();
        }
    });

    search.addEventListener("input", filterOptions);

    search.addEventListener("keydown", event => {
        switch (event.key) {
            case "ArrowDown":
                event.preventDefault();
                moveHighlight(1);
                break;

            case "ArrowUp":
                event.preventDefault();
                moveHighlight(-1);
                break;

            case "Enter": {
                event.preventDefault();

                const items = getOptions();

                if (
                    highlightedIndex >= 0 &&
                    highlightedIndex < items.length
                ) {
                    const index =
                        Number(items[highlightedIndex].dataset.index);

                    selectOption(index);

                    if (!multiple) {
                        close();
                    }
                }

                break;
            }

            case " ":
                if (highlightedIndex >= 0) {
                    event.preventDefault();

                    const items = getOptions();

                    if (items[highlightedIndex]) {
                        const index =
                            Number(items[highlightedIndex].dataset.index);

                        selectOption(index);

                        if (!multiple) {
                            close();
                        }
                    }
                }

                break;

            case "Escape":
                event.preventDefault();
                close();
                button.focus();
                break;

            case "Tab":
                close();
                break;

            case "Home":
                event.preventDefault();
                highlightedIndex = 0;
                highlightOption();
                break;

            case "End":
                event.preventDefault();

                highlightedIndex =
                    getOptions().length - 1;

                highlightOption();
                break;
        }
    });

    document.addEventListener("click", event => {
        if (!wrapper.contains(event.target)) {
            close();
        }
    });

    // Something else changed the select, such as refreshDropdown() adding a
    // new option, so rebuild and keep any search in place.
    select.addEventListener("change", () => {
        if (selecting) {
            return;
        }

        createOptions();
        filterOptions();
    });

    createOptions();
}
