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
});

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
                    refreshDropdown(dropdownId);
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

function refreshDropdown(dropdownId)
{
    let requestData = { 
        requestType: 'get-downdown-options',
        dropdownId: dropdownId
    };

    const select = document.getElementById(dropdownId);
    select.innerHTML = "<option>Loading...</option>";

    sendAjaxRequest("inc/ajax.php", requestData, 
        (data) => {
            if (select) {
                select.innerHTML = data.optionsHtml;
            }
        },
        (error) => {
            console.error("Error refreshing dropdown:", error);
        }
    );
}
