/*
  Requirement: Populate the single topic page and manage replies.
*/

// --- Global Data Store ---
let currentTopicId = null;
let currentReplies = [];

// --- Element Selections ---
// TODO: Select each element by its id
const topicSubject = document.getElementById('topic-subject');
const opMessage = document.getElementById('op-message');
const opFooter = document.getElementById('op-footer');
const replyListContainer = document.getElementById('reply-list-container');
const replyForm = document.getElementById('reply-form');
const newReplyText = document.getElementById('new-reply');

// --- Functions ---

/**
 * TODO: Implement getTopicIdFromURL.
 */
function getTopicIdFromURL() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

/**
 * TODO: Implement renderOriginalPost.
 */
function renderOriginalPost(topic) {
    topicSubject.textContent = topic.subject;
    opMessage.textContent = topic.message;
    opFooter.textContent = `Posted by: ${topic.author} on ${topic.created_at}`;
}

/**
 * TODO: Implement createReplyArticle.
 */
function createReplyArticle(reply) {
    const article = document.createElement('article');
    article.innerHTML = `
        <p>${reply.text}</p>
        <footer>Posted by: ${reply.author} on ${reply.created_at}</footer>
        <div>
            <button class="delete-reply-btn" data-id="${reply.id}">Delete</button>
        </div>
    `;
    return article;
}

/**
 * TODO: Implement renderReplies.
 */
function renderReplies() {
    // 1. Clear container
    replyListContainer.innerHTML = "";
    // 2. Loop through replies
    currentReplies.forEach(reply => {
        // 3. Append each reply
        const replyArticle = createReplyArticle(reply);
        replyListContainer.appendChild(replyArticle);
    });
}

/**
 * TODO: Implement handleAddReply (async).
 */
async function handleAddReply(event) {
    // 1. Prevent default submit
    event.preventDefault();
    // 2. Read and trim value
    const replyText = newReplyText.value.trim();
    // 3. Early return if empty
    if (!replyText) return;

    // 4. Send POST request
    const response = await fetch('./api/index.php?action=reply', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            topic_id: parseInt(currentTopicId),
            author: "Student",
            text: replyText
        })
    });

    const result = await response.json();

    // 5. On success
    if (result.success === true) {
        currentReplies.push(result.data);
        renderReplies();
        newReplyText.value = "";
    }
}

/**
 * TODO: Implement handleReplyListClick (async).
 */
async function handleReplyListClick(event) {
    // 1. Check for delete class
    if (event.target.classList.contains('delete-reply-btn')) {
        // a. Read id
        const id = event.target.dataset.id;
        // b. Send DELETE request
        const response = await fetch(`./api/index.php?action=delete_reply&id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        // c. On success, update UI
        if (result.success) {
            currentReplies = currentReplies.filter(r => r.id != id);
            renderReplies();
        }
    }
}

/**
 * TODO: Implement initializePage (async).
 */
async function initializePage() {
    // 1. Get ID from URL
    currentTopicId = getTopicIdFromURL();

    // 2. Error handling if ID missing
    if (!currentTopicId) {
        topicSubject.textContent = "Topic not found.";
        return;
    }

    try {
        // 3. Fetch topic and replies in parallel
        const [topicRes, repliesRes] = await Promise.all([
            fetch(`./api/index.php?id=${currentTopicId}`),
            fetch(`./api/index.php?action=replies&topic_id=${currentTopicId}`)
        ]);

        const topicData = await topicRes.json();
        const repliesData = await repliesRes.json();

        // 4 & 5. Store and render if topic exists
        if (topicData.success && topicData.data) {
            currentReplies = repliesData.success ? repliesData.data : [];
            
            renderOriginalPost(topicData.data);
            renderReplies();

            // Attach listeners
            replyForm.addEventListener('submit', handleAddReply);
            replyListContainer.addEventListener('click', handleReplyListClick);
        } else {
            // 6. Topic not found logic
            topicSubject.textContent = "Topic not found.";
        }
    } catch (error) {
        console.error("Initialization failed:", error);
        topicSubject.textContent = "Error loading topic.";
    }
}

// --- Initial Page Load ---
initializePage();
