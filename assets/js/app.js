document.addEventListener("DOMContentLoaded", function()
{
    document.querySelectorAll('.add-new-attribute-value').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            const requestData = {
                requestType: 'load-form',
                buttonId: this.id
            };

            sendAjaxRequest("inc/ajax.php", requestData, 
                (data) => {
                    showModalWithForm(data.formHtml, data.selectId); // Load form into modal and display
                },
                (error) => {
                    console.error("Error loading form:", error);
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

    document.querySelectorAll('a[href="#print"]').forEach(link => {
        link.addEventListener("click", event => {
            event.preventDefault();
            window.print();
        });
    });
});

/**
 * Click a heading to sort the rows under it. The first row holds the headings
 * and stays put; columns of numbers sort numerically, everything else by text.
 */
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

    const cellValue = row => (row.cells[index] ? row.cells[index].textContent.trim() : "");

    // Treat a column as numeric only when every value in it looks like a number.
    const asNumber = value => {
        const cleaned = value.replace(/[^0-9.\-]/g, "");
        return cleaned === "" || cleaned === "-" ? NaN : parseFloat(cleaned);
    };

    const numeric = rows.every(row => {
        const value = cellValue(row);
        return value === "" || !isNaN(asNumber(value));
    });

    rows.sort((a, b) => {
        const first = cellValue(a);
        const second = cellValue(b);

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

// Show modal with form
function showModalWithForm(formHtml, dropdownId)
{
    let modal = document.getElementById("custom-modal");
    
    if (!modal) {
        modal = document.createElement("div");
        modal.id = "custom-modal";
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <span class="close-modal">&times;</span>
                <div id="modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);

        // Close modal when clicking overlay or close button
        modal.querySelector(".close-modal").addEventListener("click", closeModal);
        modal.querySelector(".modal-overlay").addEventListener("click", closeModal);
    }

    document.getElementById("modal-body").innerHTML = formHtml;
    modal.style.display = "flex"; // Show the modal

    attachFormSubmitHandler(dropdownId);
}

// Attach AJAX submit to the form inside the modal
function attachFormSubmitHandler(dropdownId)
{
    const form = document.querySelector("#modal-body form");
    if (!form) return;

    form.addEventListener("submit", function(e) {
        e.preventDefault();

        const formData = new FormData(form);
        let requestData = {
            requestType: "submit-form",
            formId: dropdownId,
            formData: Object.fromEntries(formData.entries())
        };
        const errorContainer = document.getElementById("modal-error-message");
        if (errorContainer) errorContainer.innerHTML = "";

        sendAjaxRequest("inc/ajax.php", requestData, 
            (data) => {
                if (data.success) {
                    closeModal();
                    refreshDropdown(dropdownId, data.newId);
                } else {
                    showModalError(data.error || "An unknown error occurred.");
                }
            },
            (error) => {
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

function refreshDropdown(dropdownId, newId)
{
    const select = document.getElementById(dropdownId);
    if (!select) return;

    // Keep what is already chosen; a multi-select would otherwise lose it.
    const selected = Array.from(select.selectedOptions).map(option => option.value);

    let requestData = {
        requestType: 'get-downdown-options',
        dropdownId: dropdownId,
        newId: newId,
        selected: selected,
        multiple: select.multiple
    };

    select.innerHTML = "<option>Loading...</option>";

    sendAjaxRequest("inc/ajax.php", requestData,
        (data) => {
            select.innerHTML = data.optionsHtml;
            select.dispatchEvent(new Event("change", { bubbles: true }));
        },
        (error) => {
            console.error("Error refreshing dropdown:", error);
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

        Array.from(select.options).forEach((option, index) => {
            const item = document.createElement("div");

            item.className = "searchable-select__option";
            item.setAttribute("role", "option");
            item.dataset.index = index;
            item.textContent = option.textContent;

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

        select.dispatchEvent(
            new Event("change", { bubbles: true })
        );

        createOptions();
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
                // Exact match
                score = 0;
            } else if (text.startsWith(query)) {
                // Starts with search string
                score = 1;
            } else if (text.includes(query)) {
                // Contains search string
                score = 2;
            } else {
                // Doesn't match
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

        // Sort by relevance while preserving original order
        // for results with the same score.
        results.sort((a, b) => {
            if (a.score !== b.score) {
                return a.score - b.score;
            }

            return a.index - b.index;
        });

        // Rebuild the options in ranked order.
        results.forEach(result => {
            result.item.hidden = false;
            optionsContainer.appendChild(result.item);
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

    select.addEventListener("change", () => {
        createOptions();
    });

    createOptions();
}
