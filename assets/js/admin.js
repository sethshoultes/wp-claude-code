jQuery(document).ready(function($) {
    let conversationId = '';
    let currentConversationTitle = '';
    let currentAttachments = [];
    let currentModel = 'claude-3-sonnet';
    
    // Initialize interface
    function init() {
        bindEvents();
        updateConnectionStatus();
        loadConversations();
        loadSavedPrompts();
        createPromptModal();
        initializeFileAttachments();
        initializeModelSelector();
    }
    
    function bindEvents() {
        $('#send-message').on('click', sendMessage);
        $('#clear-chat').on('click', clearChat);
        $('#chat-input').on('keydown', function(e) {
            if (e.ctrlKey && e.keyCode === 13) {
                sendMessage();
            }
        });
        
        $('.action-btn').on('click', function() {
            const action = $(this).data('action');
            handleQuickAction(action);
        });
        
        $('#test-connection').on('click', testConnection);
        $('#refresh-models').on('click', refreshAvailableModels);
        
        // Conversation history events
        $('#new-conversation').on('click', startNewConversation);
        $(document).on('click', '.conversation-item', loadConversation);
        $(document).on('click', '.conversation-action', handleConversationAction);
        
        // Saved prompts events
        $('#save-prompt').on('click', showSavePromptModal);
        $('#prompt-category-filter').on('change', filterPrompts);
        $(document).on('click', '.prompt-item', usePrompt);
        $(document).on('click', '.prompt-action', handlePromptAction);
    }
    
    function sendMessage() {
        const message = $('#chat-input').val().trim();
        if (!message) return;
        
        addMessageToChat('user', message);
        $('#chat-input').val('');
        showTypingIndicator();
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_chat',
                message: message,
                conversation_id: conversationId,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                hideTypingIndicator();
                if (response.success) {
                    conversationId = response.data.conversation_id;
                    addMessageToChat('assistant', response.data.response, response.data.tools_used);
                    
                    // Refresh conversations list to show updated activity
                    loadConversations();
                } else {
                    addErrorMessage(response.data || 'An error occurred');
                }
            },
            error: function() {
                hideTypingIndicator();
                addErrorMessage('Network error occurred');
            }
        });
    }
    
    function addMessageToChat(type, content, toolsUsed = null, shouldScroll = true) {
        const messagesContainer = $('#chat-messages');
        const messageHtml = createMessageHtml(type, content, toolsUsed);
        messagesContainer.append(messageHtml);
        if (shouldScroll) {
            scrollToBottom();
        }
    }
    
    function createMessageHtml(type, content, toolsUsed = null) {
        let html = `<div class="message ${type}">`;
        html += `<div class="message-content">${formatContent(content)}</div>`;
        
        if (toolsUsed && toolsUsed.length > 0) {
            html += `<div class="tools-used">Tools used: ${toolsUsed.join(', ')}</div>`;
        }
        
        html += '</div>';
        return html;
    }
    
    function formatContent(content) {
        // Convert markdown-style code blocks
        content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
            return `<div class="code-block"><pre><code>${escapeHtml(code.trim())}</code></pre></div>`;
        });
        
        // Convert inline code
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Convert line breaks
        content = content.replace(/\n/g, '<br>');
        
        return content;
    }
    
    function escapeHtml(text) {
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    function addErrorMessage(message) {
        const messagesContainer = $('#chat-messages');
        const errorHtml = `
            <div class="message error">
                <div class="message-content" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                    Error: ${message}
                </div>
            </div>
        `;
        messagesContainer.append(errorHtml);
        scrollToBottom();
    }
    
    function showTypingIndicator() {
        const typingHtml = `
            <div class="typing-indicator" id="typing-indicator">
                Claude is thinking
                <div class="typing-dots">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        `;
        $('#chat-messages').append(typingHtml);
        scrollToBottom();
    }
    
    function hideTypingIndicator() {
        $('#typing-indicator').remove();
    }
    
    function scrollToBottom() {
        const messagesContainer = $('#chat-messages');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }
    
    function clearChat() {
        if (confirm('Clear the current conversation?')) {
            $('#chat-messages').html(`
                <div class="system-message">
                    <p>Conversation cleared. How can I help you?</p>
                </div>
            `);
            conversationId = '';
        }
    }
    
    function handleQuickAction(action) {
        const actions = {
            'site-info': 'Show me the WordPress site information and current configuration',
            'plugin-list': 'List all installed plugins and their status',
            'theme-info': 'Show information about the active theme',
            'db-status': 'Check the database status and basic statistics',
            'list-posts': 'List the latest posts on this WordPress site',
            'list-pages': 'List all pages on this WordPress site',
            'create-post': 'Help me create a new blog post with title, content, and categories',
            'create-page': 'Help me create a new page with title and content'
        };
        
        if (actions[action]) {
            $('#chat-input').val(actions[action]);
            sendMessage();
        }
    }
    
    function updateConnectionStatus() {
        // This would check the LiteLLM connection status
        // For now, we'll simulate it
        setTimeout(function() {
            $('#connection-status').text('Ready');
            $('.status-dot').addClass('connected');
        }, 1000);
    }
    
    // Conversation Management Functions
    function loadConversations() {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_conversations',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderConversations(response.data.conversations);
                } else {
                    $('#conversation-list').html('<div class="loading-placeholder">No conversations found</div>');
                }
            },
            error: function() {
                $('#conversation-list').html('<div class="loading-placeholder">Error loading conversations</div>');
            }
        });
    }
    
    function renderConversations(conversations) {
        const container = $('#conversation-list');
        
        if (conversations.length === 0) {
            container.html('<div class="loading-placeholder">No conversations yet</div>');
            return;
        }
        
        let html = '';
        conversations.forEach(function(conv) {
            const date = new Date(conv.last_activity);
            const timeAgo = formatTimeAgo(date);
            
            html += `
                <div class="conversation-item" data-conversation-id="${conv.conversation_id}">
                    <div class="conversation-title">
                        <span class="title-text">${escapeHtml(conv.title)}</span>
                        <div class="conversation-actions">
                            <button class="conversation-action" data-action="rename" title="Rename">✏️</button>
                            <button class="conversation-action" data-action="delete" title="Delete">🗑️</button>
                        </div>
                    </div>
                    <div class="conversation-meta">
                        <span>${conv.message_count} messages</span>
                        <span>${timeAgo}</span>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    function loadConversation(e) {
        e.stopPropagation();
        
        if ($(e.target).hasClass('conversation-action')) {
            return;
        }
        
        const convId = $(this).data('conversation-id');
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_conversation',
                conversation_id: convId,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    conversationId = convId;
                    renderConversationMessages(response.data.messages);
                    $('.conversation-item').removeClass('active');
                    $(`[data-conversation-id="${convId}"]`).addClass('active');
                }
            },
            error: function() {
                alert('Error loading conversation');
            }
        });
    }
    
    function renderConversationMessages(messages) {
        const container = $('#chat-messages');
        container.empty();
        
        messages.forEach(function(message) {
            const toolsUsed = message.tools_used ? JSON.parse(message.tools_used) : null;
            addMessageToChat(message.message_type, message.content, toolsUsed, false);
        });
        
        scrollToBottom();
    }
    
    function startNewConversation() {
        conversationId = '';
        currentConversationTitle = '';
        $('#chat-messages').html(`
            <div class="system-message">
                <p>Welcome to Claude Code for WordPress! I can help you with:</p>
                <ul>
                    <li>Theme and plugin development</li>
                    <li>Database queries and content management</li>
                    <li>WP-CLI commands and site management</li>
                    <li>Code analysis and debugging</li>
                    <li>Creating staging environments</li>
                </ul>
                <p>What would you like to work on?</p>
            </div>
        `);
        $('.conversation-item').removeClass('active');
    }
    
    function handleConversationAction(e) {
        e.stopPropagation();
        
        const action = $(this).data('action');
        const convId = $(this).closest('.conversation-item').data('conversation-id');
        
        if (action === 'delete') {
            if (confirm('Delete this conversation?')) {
                deleteConversation(convId);
            }
        } else if (action === 'rename') {
            const currentTitle = $(this).closest('.conversation-item').find('.title-text').text();
            const newTitle = prompt('Enter new title:', currentTitle);
            if (newTitle && newTitle !== currentTitle) {
                renameConversation(convId, newTitle);
            }
        }
    }
    
    function deleteConversation(convId) {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_delete_conversation',
                conversation_id: convId,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadConversations();
                    if (conversationId === convId) {
                        startNewConversation();
                    }
                } else {
                    alert('Error deleting conversation');
                }
            }
        });
    }
    
    function renameConversation(convId, title) {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_rename_conversation',
                conversation_id: convId,
                title: title,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadConversations();
                } else {
                    alert('Error renaming conversation');
                }
            }
        });
    }
    
    // Saved Prompts Functions
    function loadSavedPrompts() {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_saved_prompts',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSavedPrompts(response.data.prompts);
                    renderPromptCategories(response.data.categories);
                } else {
                    $('#prompt-list').html('<div class="loading-placeholder">No prompts found</div>');
                }
            },
            error: function() {
                $('#prompt-list').html('<div class="loading-placeholder">Error loading prompts</div>');
            }
        });
    }
    
    function renderSavedPrompts(prompts) {
        const container = $('#prompt-list');
        
        if (prompts.length === 0) {
            container.html('<div class="loading-placeholder">No saved prompts</div>');
            return;
        }
        
        let html = '';
        prompts.forEach(function(prompt) {
            const preview = prompt.content.substring(0, 80) + (prompt.content.length > 80 ? '...' : '');
            
            html += `
                <div class="prompt-item" data-prompt-id="${prompt.id}">
                    <div class="prompt-title">
                        <span class="title-text">${escapeHtml(prompt.title)}</span>
                        <div class="prompt-actions">
                            <button class="prompt-action" data-action="delete" title="Delete">🗑️</button>
                        </div>
                    </div>
                    <div class="prompt-preview">${escapeHtml(preview)}</div>
                    <div class="prompt-meta">
                        <span>${prompt.category}</span>
                        <span>Used ${prompt.usage_count} times</span>
                    </div>
                </div>
            `;
        });
        
        container.html(html);
    }
    
    function renderPromptCategories(categories) {
        const select = $('#prompt-category-filter');
        const currentValue = select.val();
        
        select.empty().append('<option value="">All Categories</option>');
        
        categories.forEach(function(category) {
            select.append(`<option value="${category}">${category}</option>`);
        });
        
        select.val(currentValue);
    }
    
    function filterPrompts() {
        const category = $('#prompt-category-filter').val();
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_saved_prompts',
                category: category,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    renderSavedPrompts(response.data.prompts);
                }
            }
        });
    }
    
    function usePrompt(e) {
        e.stopPropagation();
        
        if ($(e.target).hasClass('prompt-action')) {
            return;
        }
        
        const promptId = $(this).data('prompt-id');
        
        // Get the full prompt content by ID
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_prompt',
                prompt_id: promptId,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    $('#chat-input').val(response.data.content);
                    
                    // Increment usage count
                    $.ajax({
                        url: claudeCode.ajaxUrl,
                        type: 'POST',
                        data: {
                            action: 'claude_code_increment_prompt_usage',
                            prompt_id: promptId,
                            nonce: claudeCode.nonce
                        },
                        success: function() {
                            // Refresh the prompts to show updated usage count
                            loadSavedPrompts();
                        }
                    });
                }
            }
        });
    }
    
    function handlePromptAction(e) {
        e.stopPropagation();
        
        const action = $(this).data('action');
        const promptId = $(this).closest('.prompt-item').data('prompt-id');
        
        if (action === 'delete') {
            if (confirm('Delete this saved prompt?')) {
                deletePrompt(promptId);
            }
        }
    }
    
    function deletePrompt(promptId) {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_delete_prompt',
                prompt_id: promptId,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    loadSavedPrompts();
                } else {
                    alert('Error deleting prompt');
                }
            }
        });
    }
    
    function showSavePromptModal() {
        const currentMessage = $('#chat-input').val().trim();
        
        if (!currentMessage) {
            alert('Please enter a message to save as a prompt');
            return;
        }
        
        $('#prompt-modal-content').val(currentMessage);
        $('#prompt-modal').show();
    }
    
    function createPromptModal() {
        const modalHtml = `
            <div id="prompt-modal" class="prompt-modal">
                <div class="prompt-modal-content">
                    <h3>Save Prompt Template</h3>
                    <form id="save-prompt-form">
                        <label for="prompt-modal-title">Title:</label>
                        <input type="text" id="prompt-modal-title" required>
                        
                        <label for="prompt-modal-category">Category:</label>
                        <select id="prompt-modal-category">
                            <option value="general">General</option>
                            <option value="development">Development</option>
                            <option value="debugging">Debugging</option>
                            <option value="database">Database</option>
                            <option value="content">Content</option>
                        </select>
                        
                        <label for="prompt-modal-content">Prompt Content:</label>
                        <textarea id="prompt-modal-content" required></textarea>
                        
                        <div class="prompt-modal-actions">
                            <button type="button" id="cancel-prompt" class="button">Cancel</button>
                            <button type="submit" class="button button-primary">Save Prompt</button>
                        </div>
                    </form>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        $('#cancel-prompt').on('click', function() {
            $('#prompt-modal').hide();
        });
        
        $('#prompt-modal').on('click', function(e) {
            if (e.target === this) {
                $(this).hide();
            }
        });
        
        $('#save-prompt-form').on('submit', function(e) {
            e.preventDefault();
            
            const title = $('#prompt-modal-title').val().trim();
            const category = $('#prompt-modal-category').val();
            const content = $('#prompt-modal-content').val().trim();
            
            if (!title || !content) {
                alert('Please fill in all required fields');
                return;
            }
            
            $.ajax({
                url: claudeCode.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'claude_code_save_prompt',
                    title: title,
                    category: category,
                    content: content,
                    nonce: claudeCode.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#prompt-modal').hide();
                        $('#save-prompt-form')[0].reset();
                        loadSavedPrompts();
                        alert('Prompt saved successfully!');
                    } else {
                        alert('Error saving prompt');
                    }
                },
                error: function() {
                    alert('Network error occurred');
                }
            });
        });
    }
    
    // Utility Functions
    function formatTimeAgo(date) {
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);
        
        if (diffInSeconds < 60) return 'Just now';
        if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + 'm ago';
        if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + 'h ago';
        if (diffInSeconds < 2592000) return Math.floor(diffInSeconds / 86400) + 'd ago';
        
        return date.toLocaleDateString();
    }
    
    function testConnection() {
        const button = $('#test-connection');
        const result = $('#connection-result');
        
        button.prop('disabled', true).text('Testing...');
        result.hide();
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_test_connection',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                button.prop('disabled', false).text('Test Connection');
                
                if (response.success) {
                    result.removeClass('error').addClass('success')
                          .text('Connection successful!')
                          .show();
                } else {
                    result.removeClass('success').addClass('error')
                          .text('Connection failed: ' + (response.data || 'Unknown error'))
                          .show();
                }
            },
            error: function() {
                button.prop('disabled', false).text('Test Connection');
                result.removeClass('success').addClass('error')
                      .text('Network error occurred')
                      .show();
            }
        });
    }
    
    // File Attachment Functions
    function initializeFileAttachments() {
        // File upload button
        $('#attach-file').on('click', function() {
            $('#file-upload').click();
        });
        
        // File input change
        $('#file-upload').on('change', function() {
            const files = this.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
        
        // Clear attachments
        $('#clear-attachments').on('click', function() {
            currentAttachments = [];
            updateAttachmentsDisplay();
        });
        
        // Drag and drop
        const chatContainer = $('.chat-container')[0];
        
        $(chatContainer).on('dragover', function(e) {
            e.preventDefault();
            showDragOverlay();
        });
        
        $(chatContainer).on('dragleave', function(e) {
            if (!chatContainer.contains(e.relatedTarget)) {
                hideDragOverlay();
            }
        });
        
        $(chatContainer).on('drop', function(e) {
            e.preventDefault();
            hideDragOverlay();
            
            const files = e.originalEvent.dataTransfer.files;
            if (files.length > 0) {
                uploadFiles(files);
            }
        });
        
        // Create drag overlay
        if (!$('.drag-overlay').length) {
            $('body').append('<div class="drag-overlay">📎 Drop files here to attach</div>');
        }
    }
    
    function showDragOverlay() {
        $('.drag-overlay').addClass('active');
    }
    
    function hideDragOverlay() {
        $('.drag-overlay').removeClass('active');
    }
    
    function uploadFiles(files) {
        if (!files || files.length === 0) return;
        
        // Show progress
        showUploadProgress();
        
        const promises = [];
        
        for (let i = 0; i < files.length; i++) {
            promises.push(uploadFile(files[i]));
        }
        
        Promise.all(promises).then(function(results) {
            hideUploadProgress();
            
            results.forEach(function(result) {
                if (result.success) {
                    currentAttachments.push(result.data);
                    
                    // Auto-switch to vision model when image is uploaded
                    if (result.data.is_image) {
                        const visionModels = ['claude-3-sonnet', 'claude-3-opus', 'claude-3-haiku', 'gpt-4o', 'gpt-4o-mini'];
                        const openaiModels = ['gpt-4o', 'gpt-4o-mini'];
                        const claudeModels = ['claude-3-sonnet', 'claude-3-opus', 'claude-3-haiku'];
                        
                        let shouldSwitch = false;
                        let preferredVisionModel = currentModel;
                        
                        // Always check if we should switch to a better model for image processing
                        if (!visionModels.includes(currentModel)) {
                            // Current model doesn't support vision at all
                            shouldSwitch = true;
                            preferredVisionModel = getSmartVisionModel();
                        } else {
                            // Current model supports vision, but check if we should switch for format compatibility
                            // Get the smart model preference
                            const smartModel = getSmartVisionModel();
                            
                            // If smart model suggests a different model (e.g., GPT-4o-mini when on Claude), switch
                            if (smartModel !== currentModel && openaiModels.includes(smartModel) && claudeModels.includes(currentModel)) {
                                shouldSwitch = true;
                                preferredVisionModel = smartModel;
                            }
                        }
                        
                        if (shouldSwitch) {
                            setTimeout(() => {
                                const oldModelName = getModelDisplayName(currentModel);
                                const newModelName = getModelDisplayName(preferredVisionModel);
                                
                                // Auto-switch without asking
                                $('#model-selector').val(preferredVisionModel).trigger('change');
                                
                                // Show notification
                                showNotification(`🖼️ Automatically switched from ${oldModelName} to ${newModelName} for better LiteLLM compatibility`, 'info');
                            }, 500);
                        }
                    }
                } else {
                    alert('Failed to upload ' + result.filename + ': ' + result.error);
                }
            });
            
            updateAttachmentsDisplay();
            
        }).catch(function(error) {
            hideUploadProgress();
            alert('Upload failed: ' + error);
        });
    }
    
    function uploadFile(file) {
        return new Promise(function(resolve, reject) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('conversation_id', conversationId);
            formData.append('action', 'claude_code_upload_file');
            formData.append('nonce', claudeCode.nonce);
            
            $.ajax({
                url: claudeCode.ajaxUrl,
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if (response.success) {
                        resolve({
                            success: true,
                            data: response.data,
                            filename: file.name
                        });
                    } else {
                        resolve({
                            success: false,
                            error: response.data,
                            filename: file.name
                        });
                    }
                },
                error: function() {
                    resolve({
                        success: false,
                        error: 'Network error',
                        filename: file.name
                    });
                }
            });
        });
    }
    
    function showUploadProgress() {
        if (!$('.upload-progress').length) {
            const progressHtml = `
                <div class="upload-progress">
                    <div>Uploading files...</div>
                    <div class="upload-progress-bar">
                        <div class="upload-progress-fill" style="width: 0%"></div>
                    </div>
                </div>
            `;
            $('body').append(progressHtml);
        }
        $('.upload-progress').show();
        
        // Animate progress bar
        let progress = 0;
        const interval = setInterval(function() {
            progress += 10;
            $('.upload-progress-fill').css('width', progress + '%');
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 100);
    }
    
    function hideUploadProgress() {
        $('.upload-progress').remove();
    }
    
    function updateAttachmentsDisplay() {
        const container = $('#file-attachments');
        const list = $('#attachments-list');
        
        if (currentAttachments.length === 0) {
            container.hide();
            return;
        }
        
        container.show();
        list.empty();
        
        currentAttachments.forEach(function(attachment, index) {
            const icon = getFileIcon(attachment.type);
            const size = formatFileSize(attachment.size);
            
            const attachmentHtml = `
                <div class="attachment-item" data-index="${index}">
                    <span class="attachment-icon">${icon}</span>
                    <span class="attachment-name" title="${escapeHtml(attachment.name)}">${escapeHtml(attachment.name)}</span>
                    <span class="attachment-size">(${size})</span>
                    <button class="attachment-remove" data-index="${index}" title="Remove">×</button>
                </div>
            `;
            list.append(attachmentHtml);
        });
        
        // Bind remove events
        $('.attachment-remove').on('click', function() {
            const index = parseInt($(this).data('index'));
            currentAttachments.splice(index, 1);
            updateAttachmentsDisplay();
        });
    }
    
    function getFileIcon(mimeType) {
        if (mimeType.startsWith('image/')) return '🖼️';
        if (mimeType.startsWith('text/')) return '📄';
        if (mimeType.includes('json')) return '📋';
        if (mimeType.includes('pdf')) return '📕';
        if (mimeType.includes('zip')) return '📦';
        if (mimeType.includes('javascript')) return '📜';
        if (mimeType.includes('css')) return '🎨';
        if (mimeType.includes('html')) return '🌐';
        if (mimeType.includes('sql')) return '🗄️';
        return '📎';
    }
    
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
    
    // Update sendMessage function to include attachments
    const originalSendMessage = sendMessage;
    sendMessage = function() {
        const message = $('#chat-input').val().trim();
        if (!message) return;
        
        // Add attachment info to message if attachments exist
        let messageWithAttachments = message;
        if (currentAttachments.length > 0) {
            messageWithAttachments += '\n\n[Attachments: ';
            messageWithAttachments += currentAttachments.map(att => att.name).join(', ');
            messageWithAttachments += ']';
        }
        
        addMessageToChat('user', messageWithAttachments);
        $('#chat-input').val('');
        showTypingIndicator();
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_chat',
                message: message,
                conversation_id: conversationId,
                attachments: currentAttachments.map(att => att.id),
                nonce: claudeCode.nonce
            },
            success: function(response) {
                hideTypingIndicator();
                if (response.success) {
                    conversationId = response.data.conversation_id;
                    addMessageToChat('assistant', response.data.response, response.data.tools_used);
                    
                    // Clear attachments after sending
                    currentAttachments = [];
                    updateAttachmentsDisplay();
                    
                    // Refresh conversations list to show updated activity
                    loadConversations();
                } else {
                    addErrorMessage(response.data || 'An error occurred');
                }
            },
            error: function() {
                hideTypingIndicator();
                addErrorMessage('Network error occurred');
            }
        });
    };
    
    // Model Selector Functions
    function initializeModelSelector() {
        // Load saved model preference from localStorage first
        const savedModel = localStorage.getItem('claude_code_model');
        if (savedModel) {
            currentModel = savedModel;
            $('#model-selector').val(currentModel);
        }
        
        // Load available models from server
        loadAvailableModels();
        
        // Load user's server-side preference
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_user_model',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success && response.data.model) {
                    currentModel = response.data.model;
                    $('#model-selector').val(currentModel);
                    localStorage.setItem('claude_code_model', currentModel);
                    updateModelDisplay();
                }
            }
        });
        
        // Model change handler
        $('#model-selector').on('change', function() {
            const newModel = $(this).val();
            changeModel(newModel);
        });
        
        // Set initial model display
        updateModelDisplay();
    }
    
    function changeModel(newModel) {
        if (newModel === currentModel) return;
        
        const oldModel = currentModel;
        currentModel = newModel;
        
        // Save preference
        localStorage.setItem('claude_code_model', currentModel);
        
        // Update display
        updateModelDisplay();
        
        // Add system message about model change
        const modelNames = {
            'claude-3-sonnet': 'Claude 3 Sonnet',
            'claude-3-opus': 'Claude 3 Opus', 
            'claude-3-haiku': 'Claude 3 Haiku',
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o Mini',
            'gpt-4': 'GPT-4',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo'
        };
        
        const systemMessage = `
            <div class="system-message model-change">
                <p>🔄 <strong>Model changed:</strong> ${modelNames[oldModel]} → ${modelNames[newModel]}</p>
                <p><small>The assistant will now use ${modelNames[newModel]} for responses.</small></p>
            </div>
        `;
        
        $('#chat-messages').append(systemMessage);
        scrollToBottom();
        
        // Send model change to server
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_set_model',
                model: currentModel,
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (!response.success) {
                    console.warn('Failed to save model preference on server');
                }
            }
        });
    }
    
    function updateModelDisplay() {
        const modelNames = {
            'claude-3-sonnet': 'Claude 3 Sonnet',
            'claude-3-opus': 'Claude 3 Opus',
            'claude-3-haiku': 'Claude 3 Haiku',
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o Mini',
            'gpt-4': 'GPT-4',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo'
        };
        
        // Update model indicator in interface
        const currentModelName = modelNames[currentModel] || currentModel;
        
        // Add model info to status area if needed
        if (!$('#model-info').length) {
            $('.status-indicator').append(`<div id="model-info" style="font-size: 11px; color: #666; margin-top: 4px;">Model: <span id="current-model-name">${currentModelName}</span></div>`);
        } else {
            $('#current-model-name').text(currentModelName);
        }
    }
    
    // Update the createMessageHtml function to include model indicator
    const originalCreateMessageHtml = createMessageHtml;
    createMessageHtml = function(type, content, toolsUsed = null) {
        let html = `<div class="message ${type}">`;
        
        // Add model indicator for assistant messages
        if (type === 'assistant') {
            const modelShortNames = {
                'claude-3-sonnet': 'Sonnet',
                'claude-3-opus': 'Opus',
                'claude-3-haiku': 'Haiku',
                'gpt-4o': 'GPT-4o',
                'gpt-4o-mini': '4o-mini',
                'gpt-4': 'GPT-4',
                'gpt-3.5-turbo': 'GPT-3.5'
            };
            const modelShortName = modelShortNames[currentModel] || currentModel;
            html += `<div class="model-indicator">${modelShortName}</div>`;
        }
        
        html += `<div class="message-content">${formatContent(content)}</div>`;
        
        if (toolsUsed && toolsUsed.length > 0) {
            html += `<div class="tools-used">Tools used: ${toolsUsed.join(', ')}</div>`;
        }
        
        html += '</div>';
        return html;
    };
    
    // Update sendMessage to include current model
    const originalSendMessageImplementation = sendMessage;
    sendMessage = function() {
        const message = $('#chat-input').val().trim();
        if (!message) return;
        
        // Add attachment info to message if attachments exist
        let messageWithAttachments = message;
        if (currentAttachments.length > 0) {
            messageWithAttachments += '\n\n[Attachments: ';
            messageWithAttachments += currentAttachments.map(att => att.name).join(', ');
            messageWithAttachments += ']';
        }
        
        addMessageToChat('user', messageWithAttachments);
        $('#chat-input').val('');
        showTypingIndicator();
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_chat',
                message: message,
                conversation_id: conversationId,
                attachments: currentAttachments.map(att => att.id),
                model: currentModel, // Include current model
                nonce: claudeCode.nonce
            },
            success: function(response) {
                hideTypingIndicator();
                if (response.success) {
                    conversationId = response.data.conversation_id;
                    addMessageToChat('assistant', response.data.response, response.data.tools_used);
                    
                    // Clear attachments after sending
                    currentAttachments = [];
                    updateAttachmentsDisplay();
                    
                    // Refresh conversations list to show updated activity
                    loadConversations();
                } else {
                    let errorMessage = response.data || 'An error occurred';
                    
                    // Check for image-related errors and provide helpful feedback
                    if (errorMessage.includes('image_url') || errorMessage.includes('Invalid user message')) {
                        errorMessage = '🖼️ Image processing error: The image format may not be compatible with the current model or LiteLLM configuration. Try switching to a different vision model or check your LiteLLM proxy settings.';
                        
                        // Show notification with suggestion
                        showNotification('Try switching to GPT-4o or Claude 3 Sonnet for better image compatibility', 'warning');
                        
                        // Add debug button for troubleshooting
                        setTimeout(() => {
                            $('#chat-messages').append(`
                                <div class="debug-helper" style="background: #f0f8ff; border: 1px solid #0073aa; border-radius: 4px; padding: 10px; margin: 10px 0;">
                                    <strong>🔧 Image Processing Debug</strong><br>
                                    <button id="debug-image-config" class="button button-small" style="margin-top: 5px;">Check Configuration</button>
                                    <div id="debug-results" style="margin-top: 10px; font-size: 12px;"></div>
                                </div>
                            `);
                            
                            $('#debug-image-config').on('click', function() {
                                $(this).prop('disabled', true).text('Checking...');
                                
                                $.ajax({
                                    url: claudeCode.ajaxUrl,
                                    type: 'POST',
                                    data: {
                                        action: 'claude_code_debug_image',
                                        nonce: claudeCode.nonce
                                    },
                                    success: function(response) {
                                        if (response.success) {
                                            const info = response.data;
                                            let html = '<strong>Configuration Status:</strong><br>';
                                            html += `• Current Model: ${info.current_model}<br>`;
                                            html += `• Vision Support: ${info.model_supports_vision ? '✅ Yes' : '❌ No'}<br>`;
                                            html += `• Image Format: ${info.image_format_override}<br>`;
                                            html += `• LiteLLM Endpoint: ${info.endpoint_suggests_litellm ? '✅ Detected' : '❓ Direct API'}<br>`;
                                            html += `• API Key: ${info.api_key_configured ? '✅ Set' : '❌ Missing'}<br>`;
                                            
                                            if (!info.model_supports_vision) {
                                                html += '<br><strong style="color: #dc3232;">⚠️ Issue Found:</strong> Current model doesn\'t support images!<br>';
                                                html += 'Switch to: Claude 3 Sonnet, GPT-4o, or GPT-4o-mini';
                                            }
                                            
                                            $('#debug-results').html(html);
                                        }
                                        $('#debug-image-config').prop('disabled', false).text('Check Configuration');
                                    }
                                });
                            });
                        }, 1000);
                    } else if (errorMessage.includes('APIConnectionError')) {
                        errorMessage = '🔌 Connection error: Unable to connect to the AI service. Please check your LiteLLM proxy configuration and API key.';
                    }
                    
                    addErrorMessage(errorMessage);
                }
            },
            error: function() {
                hideTypingIndicator();
                addErrorMessage('Network error occurred');
            }
        });
    };
    
    // Helper Functions
    function getBestVisionModel() {
        // Prioritize models based on performance and cost
        const visionModelPriority = [
            'claude-3-sonnet',    // Best balance of performance/cost
            'gpt-4o-mini',        // Fast and cost-effective  
            'claude-3-haiku',     // Fastest Claude model
            'gpt-4o',             // Most capable but expensive
            'claude-3-opus'       // Most capable Claude but slowest
        ];
        
        // Return the first available model from priority list
        for (const model of visionModelPriority) {
            if ($(`#model-selector option[value="${model}"]`).length > 0) {
                return model;
            }
        }
        
        // Fallback to first vision model available
        return 'claude-3-sonnet';
    }
    
    function getSmartVisionModel() {
        // Check if we need to get format preference from server
        // For now, we'll use a smart approach based on LiteLLM compatibility
        
        // Prioritize OpenAI models for LiteLLM compatibility
        const openaiVisionModels = ['gpt-4o-mini', 'gpt-4o'];
        const claudeVisionModels = ['claude-3-sonnet', 'claude-3-haiku', 'claude-3-opus'];
        
        // Try OpenAI models first for better LiteLLM compatibility
        for (const model of openaiVisionModels) {
            if ($(`#model-selector option[value="${model}"]`).length > 0) {
                return model;
            }
        }
        
        // Fallback to Claude models
        for (const model of claudeVisionModels) {
            if ($(`#model-selector option[value="${model}"]`).length > 0) {
                return model;
            }
        }
        
        // Final fallback
        return 'gpt-4o-mini';
    }
    
    function getModelDisplayName(model) {
        const modelNames = {
            'claude-3-sonnet': 'Claude 3 Sonnet',
            'claude-3-opus': 'Claude 3 Opus',
            'claude-3-haiku': 'Claude 3 Haiku',
            'gpt-4o': 'GPT-4o',
            'gpt-4o-mini': 'GPT-4o Mini',
            'gpt-4': 'GPT-4',
            'gpt-3.5-turbo': 'GPT-3.5 Turbo'
        };
        return modelNames[model] || model;
    }
    
    function showNotification(message, type = 'info') {
        // Create notification element if it doesn't exist
        if (!$('#claude-notification').length) {
            $('body').append(`
                <div id="claude-notification" style="
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    z-index: 10000;
                    max-width: 350px;
                    padding: 12px 16px;
                    border-radius: 6px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.15);
                    font-size: 13px;
                    display: none;
                "></div>
            `);
        }
        
        const notification = $('#claude-notification');
        
        // Set colors based on type
        const colors = {
            'info': { bg: '#e1f5fe', border: '#0288d1', text: '#0277bd' },
            'success': { bg: '#e8f5e8', border: '#4caf50', text: '#2e7d32' },
            'warning': { bg: '#fff3e0', border: '#ff9800', text: '#f57c00' },
            'error': { bg: '#ffebee', border: '#f44336', text: '#d32f2f' }
        };
        
        const color = colors[type] || colors.info;
        
        notification.css({
            'background-color': color.bg,
            'border': `1px solid ${color.border}`,
            'color': color.text
        }).html(message).fadeIn(300);
        
        // Auto-hide after 4 seconds
        setTimeout(() => {
            notification.fadeOut(300);
        }, 4000);
    }
    
    // Model Discovery Functions
    function loadAvailableModels() {
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_get_available_models',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateModelSelectorOptions(response.data.models);
                    
                    // Show status in console for debugging
                    if (response.data.is_fallback) {
                        console.warn('WP Claude Code: Using fallback models - LiteLLM proxy not accessible');
                    } else {
                        console.log('WP Claude Code: Loaded ' + response.data.total_models + ' models from LiteLLM proxy');
                    }
                } else {
                    console.error('WP Claude Code: Failed to load models:', response.data);
                }
            },
            error: function() {
                console.error('WP Claude Code: Network error while loading models');
            }
        });
    }
    
    function refreshAvailableModels() {
        const button = $('#refresh-models');
        button.prop('disabled', true).html('⟳');
        
        // Update connection status
        $('#connection-status').text('Refreshing models...');
        $('.status-dot').removeClass('connected').addClass('loading');
        
        $.ajax({
            url: claudeCode.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_refresh_models',
                nonce: claudeCode.nonce
            },
            success: function(response) {
                if (response.success) {
                    updateModelSelectorOptions(response.data.models);
                    
                    // Show notification
                    if (response.data.is_fallback) {
                        showNotification('⚠️ Using fallback models - LiteLLM proxy not accessible', 'warning');
                        $('#connection-status').text('Using fallback models');
                    } else {
                        showNotification('✅ Models refreshed! Found ' + response.data.total_models + ' models', 'success');
                        $('#connection-status').text('Ready');
                        $('.status-dot').removeClass('loading').addClass('connected');
                    }
                } else {
                    showNotification('❌ Failed to refresh models: ' + (response.data || 'Unknown error'), 'error');
                    $('#connection-status').text('Model refresh failed');
                }
            },
            error: function() {
                showNotification('❌ Network error while refreshing models', 'error');
                $('#connection-status').text('Network error');
            },
            complete: function() {
                button.prop('disabled', false).html('🔄');
                $('.status-dot').removeClass('loading');
            }
        });
    }
    
    function updateModelSelectorOptions(models) {
        const selector = $('#model-selector');
        const currentValue = selector.val();
        
        // Clear existing options
        selector.empty();
        
        // Add Claude models
        if (models.claude && models.claude.length > 0) {
            const claudeGroup = $('<optgroup label="Claude Models (Vision Support)"></optgroup>');
            models.claude.forEach(function(model) {
                const icon = model.supports_vision ? ' 🖼️' : '';
                const option = $(`<option value="${model.id}">${model.name}${icon}</option>`);
                if (model.description) {
                    option.attr('title', model.description);
                }
                claudeGroup.append(option);
            });
            selector.append(claudeGroup);
        }
        
        // Add OpenAI models - separate vision and text-only
        if (models.openai && models.openai.length > 0) {
            const visionModels = models.openai.filter(m => m.supports_vision);
            const textModels = models.openai.filter(m => !m.supports_vision);
            
            if (visionModels.length > 0) {
                const visionGroup = $('<optgroup label="OpenAI Models (Vision Support)"></optgroup>');
                visionModels.forEach(function(model) {
                    const option = $(`<option value="${model.id}">${model.name} 🖼️</option>`);
                    if (model.description) {
                        option.attr('title', model.description);
                    }
                    visionGroup.append(option);
                });
                selector.append(visionGroup);
            }
            
            if (textModels.length > 0) {
                const textGroup = $('<optgroup label="OpenAI Models (Text Only)"></optgroup>');
                textModels.forEach(function(model) {
                    const option = $(`<option value="${model.id}">${model.name}</option>`);
                    if (model.description) {
                        option.attr('title', model.description);
                    }
                    textGroup.append(option);
                });
                selector.append(textGroup);
            }
        }
        
        // Add other models
        if (models.other && models.other.length > 0) {
            const otherGroup = $('<optgroup label="Other Models"></optgroup>');
            models.other.forEach(function(model) {
                const icon = model.supports_vision ? ' 🖼️' : '';
                const option = $(`<option value="${model.id}">${model.name}${icon}</option>`);
                if (model.description) {
                    option.attr('title', model.description);
                }
                otherGroup.append(option);
            });
            selector.append(otherGroup);
        }
        
        // Restore previous selection if it still exists
        if (currentValue && selector.find(`option[value="${currentValue}"]`).length > 0) {
            selector.val(currentValue);
        } else if (selector.find('option').length > 0) {
            // Select first available model if current selection is no longer available
            const firstOption = selector.find('option').first().val();
            selector.val(firstOption);
            if (currentValue !== firstOption) {
                changeModel(firstOption);
            }
        }
    }
    
    // Initialize the interface
    init();
});