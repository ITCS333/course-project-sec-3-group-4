/*
  Requirement: Make the "Discussion Board" page interactive.
*/

// --- Global Data Store ---
let topics = [];

// --- Element Selections ---
// TODO: Select the new-topic form by id 'new-topic-form'.
const newTopicForm = document.getElementById('new-topic-form');

// TODO: Select the topic list container by id 'topic-list-container'.
const topicListContainer = document.getElementById('topic-list-container');

// --- Functions ---

/**
 * TODO: Implement createTopicArticle.
 */
function createTopicArticle(topic) {
    const article = document.createElement('article');
    
    article.innerHTML = `
        <h3><a href="topic.html?id=${topic.id}">${topic.subject}</a></h3>
        <footer>Posted by: ${topic.author} on ${topic.created_at}</footer>
        <div>
            <button class="edit-btn" data-id="${topic.id}">Edit</button>
            <button class="delete-btn" data-id="${topic.id}">Delete</button>
        </div>
    `;
    
    return article;
}

/**
 * TODO: Implement renderTopics.
 */
function renderTopics() {
    // 1. Clear the topicListContainer
    topicListContainer.innerHTML = "";
    
    // 2. Loop through the global topics array
    topics.forEach(topic => {
        // 3. Create and append the article
        const topicArticle = createTopicArticle(topic);
        topicListContainer.appendChild(topicArticle);
    });
}

/**
 * TODO: Implement handleCreateTopic (async).
 */
async function handleCreateTopic(event) {
    event.preventDefault();

    const subjectInput = document.getElementById('topic-subject');
    const messageInput = document.getElementById('topic-message');
    const submitBtn = document.getElementById('create-topic');

    const subject = subjectInput.value;
    const message = messageInput.value;

    // Check if we are updating or creating
    const editId = submitBtn.getAttribute('data-edit-id');

    if (editId) {
        // Handle Update
        await handleUpdateTopic(parseInt(editId), { subject, message });
        // Reset button state
        submitBtn.textContent = "Create Topic";
        submitBtn.removeAttribute('data-edit-id');
    } else {
        // Handle Create
        const response = await fetch('./api/index.php', {
            method: 'POST',
            headers: { 'Content-Type:': 'application/json' },
            body: JSON.stringify({ subject, message, author: "Student" })
        });

        const result = await response.json();

        if (result.success === true) {
            // Add new topic to local array (API should return the new object or at least the ID)
            const newTopic = {
                id: result.id,
                subject: subject,
                message: message,
                author: "Student",
                created_at: new Date().toISOString().slice(0, 19).replace('T', ' ')
            };
            topics.push(newTopic);
            renderTopics();
        }
    }
    newTopicForm.reset();
}

/**
 * TODO: Implement handleUpdateTopic (async).
 */
async function handleUpdateTopic(id, fields) {
    const response = await fetch('./api/index.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, ...fields })
    });

    const result = await response.json();

    if (result.success) {
        // Update the matching entry in global topics array
        const index = topics.findIndex(t => t.id === id);
        if (index !== -1) {
            topics[index].subject = fields.subject;
            topics[index].message = fields.message;
            renderTopics();
        }
    }
}

/**
 * TODO: Implement handleTopicListClick (async).
 */
async function handleTopicListClick(event) {
    const target = event.target;
    const id = parseInt(target.dataset.id);

    // 1. Delete Logic
    if (target.classList.contains('delete-btn')) {
        const response = await fetch(`./api/index.php?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();
        
        if (result.success) {
            topics = topics.filter(t => t.id !== id);
            renderTopics();
        }
    }

    // 2. Edit Logic
    if (target.classList.contains('edit-btn')) {
        const topicToEdit = topics.find(t => t.id === id);
        if (topicToEdit) {
            document.getElementById('topic-subject').value = topicToEdit.subject;
            document.getElementById('topic-message').value = topicToEdit.message;
            
            const submitBtn = document.getElementById('create-topic');
            submitBtn.textContent = "Update Topic";
            submitBtn.setAttribute('data-edit-id', id);
        }
    }
}

/**
 * TODO: Implement loadAndInitialize (async).
 */
async function loadAndInitialize() {
    try {
        // 1. Fetch topics
        const response = await fetch('./api/index.php');
        const result = await response.json();

        if (result.success === true) {
            // 2. Store data
            topics = result.data;
            // 3. Render
            renderTopics();
        }

        // 4. Attach Form Listener
        newTopicForm.addEventListener('submit', handleCreateTopic);

        // 5. Attach List Listener (Delegation)
        topicListContainer.addEventListener('click', handleTopicListClick);

    } catch (error) {
        console.error("Initialization failed:", error);
    }
}

// --- Initial Page Load ---
loadAndInitialize();
