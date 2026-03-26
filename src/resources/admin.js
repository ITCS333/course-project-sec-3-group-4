/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. Link this file to `admin.html` using:
     <script src="admin.js" defer></script>
     
  
  2. In `admin.html`, add id="resources-tbody" to the <tbody> element
     inside your resources-table. This id is required by this script.
  
  3. Implement the TODOs below.
*/

// --- Global Data Store ---
// This will hold the resources loaded from the API.


// --- Element Selections ---
// TODO: Select the resource form ('#resource-form').


// TODO: Select the resources table body ('#resources-tbody').

// --- Functions ---

/**
 * TODO: Implement the createResourceRow function.
 * It takes one resource object { id, title, description, link }.
 * It should return a <tr> element with the following <td>s:
 * 1. A <td> for the title.
 * 2. A <td> for the description.
 * 3. A <td> for the link.
 * 4. A <td> containing two buttons:
 *    - An "Edit" button with class="edit-btn" and data-id="${id}".
 *    - A "Delete" button with class="delete-btn" and data-id="${id}".
 */
  // ... your implementation here ...

/**
 * TODO: Implement the renderTable function.
 * It should:
 * 1. Clear the resources table body ('#resources-tbody').
 * 2. Loop through the global `resources` array.
 * 3. For each resource, call `createResourceRow()` and
 *    append the returned <tr> to the table body.
 */

/**
 * Renders the resources table based on the global `resources` array
 */
// ... your implementation here ...


/**
 * TODO: Implement the handleAddResource function.
 * This is the event handler for the form's 'submit' event.
 * It should:
 * 1. Prevent the form's default submission.
 * 2. Get the values from the title (id="resource-title"),
 *    description (id="resource-description"), and
 *    link (id="resource-link") inputs.
 * 3. Use `fetch()` to POST the new resource to the API:
 *    - URL: './api/index.php'
 *    - Method: POST
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ title, description, link })
 * 4. The API returns { success: true, id: <new id> }.
 *    Add the new resource object (including the id returned by the API)
 *    to the global `resources` array.
 * 5. Call `renderTable()` to refresh the list.
 * 6. Reset the form.
 */
 // ... your implementation here ...


/**
 * TODO: Implement the handleTableClick function.
 * This handles click events on the table body using event delegation.
 * It should:
 *
 * If the clicked element has class "delete-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Use `fetch()` to DELETE the resource via the API:
 *    - URL: `./api/index.php?id=${id}`
 *    - Method: DELETE
 * 3. On success, remove the resource from the global `resources` array
 *    by filtering out the entry with the matching id.
 * 4. Call `renderTable()` to refresh the list.
 *
 * If the clicked element has class "edit-btn":
 * 1. Get the resource id from the button's data-id attribute.
 * 2. Find the matching resource in the global `resources` array.
 * 3. Populate the form fields (id="resource-title", id="resource-description",
 *    id="resource-link") with the resource's current values so the admin
 *    can edit them.
 * 4. Change the submit button (id="add-resource") text to "Update Resource"
 *    to indicate edit mode.
 * 5. On form submit, use `fetch()` to PUT the updated resource to the API:
 *    - URL: './api/index.php'
 *    - Method: PUT
 *    - Headers: { 'Content-Type': 'application/json' }
 *    - Body: JSON.stringify({ id, title, description, link })
 * 6. On success, update the matching resource in the global `resources` array.
 * 7. Call `renderTable()` and reset the form back to "Add" mode,
 *    restoring the submit button text to "Add Resource".
 */ 
// ... your implementation here ...


/**
 * TODO: Implement the loadAndInitialize function.
 * This function must be 'async'.
 * It should:
 * 1. Use `fetch()` to GET all resources from the API:
 *    - URL: './api/index.php'
 *    - The API returns { success: true, data: [...] }
 * 2. Store the resources array (from `data`) in the global `resources` variable.
 * 3. Call `renderTable()` to populate the table for the first time.
 * 4. Add the 'submit' event listener to the resource form (id="resource-form"),
 *    calling `handleAddResource`.
 * 5. Add the 'click' event listener to the table body (id="resources-tbody"),
 *    calling `handleTableClick`.
 */
  // ... your implementation here .


/*
  Requirement: Make the "Manage Resources" page interactive.

  Instructions:
  1. This file is already linked to `admin.html` via:
         <script src="admin.js" defer></script>

  2. In `admin.html`:
     - The form has id="resource-form".
     - The submit button has id="add-resource".
     - The <tbody> has id="resources-tbody".
     - Columns rendered per row: Title | Description | Link | Actions.

  3. Implement the TODOs below.

  API base URL: ./api/index.php
  All requests and responses use JSON.
  Successful list response shape: { success: true, data: [ ...resource objects ] }
  Each resource object shape:
    {
      id:          number,
      title:       string,
      description: string,
      link:        string
    }
*/


let resources = [];
let editingResourceId = null;


const resourceForm   = document.getElementById("resource-form");
const resourcesTbody = document.getElementById("resources-tbody");


const titleInput   = document.getElementById("resource-title");
const descInput    = document.getElementById("resource-description");
const linkInput    = document.getElementById("resource-link");
const submitButton = document.getElementById("add-resource");


function createResourceRow(resource) {
  const tr = document.createElement("tr");

  const titleTd = document.createElement("td");
  titleTd.textContent = resource.title;

  const descTd = document.createElement("td");
  descTd.textContent = resource.description;

  const linkTd = document.createElement("td");
  linkTd.textContent = resource.link;

  const actionsTd = document.createElement("td");

  const editBtn = document.createElement("button");
  editBtn.textContent = "Edit";
  editBtn.classList.add("edit-btn");
  editBtn.setAttribute("data-id", resource.id);

  const deleteBtn = document.createElement("button");
  deleteBtn.textContent = "Delete";
  deleteBtn.classList.add("delete-btn");
  deleteBtn.setAttribute("data-id", resource.id);

  actionsTd.appendChild(editBtn);
  actionsTd.appendChild(deleteBtn);

  tr.appendChild(titleTd);
  tr.appendChild(descTd);
  tr.appendChild(linkTd);
  tr.appendChild(actionsTd);

  return tr;
}


function renderTable() {
  resourcesTbody.innerHTML = "";
  resources.forEach((resource) => {
    const row = createResourceRow(resource);
    resourcesTbody.appendChild(row);
  });
}


async function handleAddResource(event) {
  event.preventDefault();

  const title       = titleInput.value.trim();
  const description = descInput.value.trim();
  const link        = linkInput.value.trim();

  if (!title) return;

  const editId = submitButton.dataset.editId;

  if (editId) {
    await handleUpdateResource(editId, { title, description, link });
    delete submitButton.dataset.editId;
    submitButton.textContent = "Add Resource";
  } else {
    const res = await fetch("./api/index.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ title, description, link }),
    });
    const result = await res.json();
    if (result.success) {
      resources.push({ id: result.id, title, description, link });
      renderTable();
      resourceForm.reset();
    }
  }
}


async function handleUpdateResource(id, fields) {
  const res = await fetch("./api/index.php", {
    method: "PUT",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ id, ...fields }),
  });

  const result = await res.json();
  if (result.success) {
    const index = resources.findIndex((r) => r.id == id);
    if (index !== -1) {
      resources[index] = { id: Number(id), ...fields };
    }

    renderTable();
    resourceForm.reset();

    submitButton.textContent = "Add Resource";
    delete submitButton.dataset.editId;
  }
}


async function handleTableClick(event) {
  const target = event.target;

  
  if (target.classList.contains("delete-btn")) {
    const idToDelete = parseInt(target.dataset.id, 10);

    try {
      const response = await fetch(`./api/index.php?id=${idToDelete}`, {
        method: "DELETE",
      });

      if (!response.ok) {
        throw new Error("Failed to delete resource from server");
      }

      resources = resources.filter((r) => r.id !== idToDelete);
      renderTable();
    } catch (error) {
      console.error("Error deleting resource:", error);
    }

    return;
  }

  
  if (target.classList.contains("edit-btn")) {
    const idToEdit       = parseInt(target.dataset.id, 10);
    const resourceToEdit = resources.find((r) => r.id === idToEdit);
    if (!resourceToEdit) return;

    document.querySelector("#resource-title").value       = resourceToEdit.title       || "";
    document.querySelector("#resource-description").value = resourceToEdit.description || "";
    document.querySelector("#resource-link").value        = resourceToEdit.link        || "";

    const submitButton = document.querySelector("#add-resource");
    submitButton.textContent    = "Update Resource";
    submitButton.dataset.editId = idToEdit;

    if (typeof window.scrollTo === "function") {
      window.scrollTo({ top: 0, behavior: "smooth" });
    }
  }
}


async function loadAndInitialize() {
  try {
    const response = await fetch("./api/index.php");
    if (!response.ok) {
      throw new Error("Failed to fetch resources from API");
    }

    const result = await response.json();
    if (result.success && Array.isArray(result.data)) {
      resources = result.data;
    } else {
      resources = [];
      console.error("Unexpected API response:", result);
    }
  } catch (error) {
    console.error("Error loading resources:", error);
    resources = [];
  }

  renderTable();

  const resourceForm = document.querySelector("#resource-form");
  if (resourceForm) {
    resourceForm.addEventListener("submit", handleAddResource);
  }

  const resourcesTbody = document.querySelector("#resources-tbody");
  if (resourcesTbody) {
    resourcesTbody.addEventListener("click", handleTableClick);
  }
}

loadAndInitialize();