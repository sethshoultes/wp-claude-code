jQuery(document).ready(function($) {
    let conversationId = '';
    
    // Initialize interface
    function init() {
        bindEvents();
        updateConnectionStatus();
        loadConversationHistory();
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
    
    function addMessageToChat(type, content, toolsUsed = null) {
        const messagesContainer = $('#chat-messages');
        const messageHtml = createMessageHtml(type, content, toolsUsed);
        messagesContainer.append(messageHtml);
        scrollToBottom();
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
            'db-status': 'Check the database status and basic statistics'
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
    
    function loadConversationHistory() {
        // Load recent conversation if available
        // This could be implemented to restore the last conversation
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
    
    // Initialize the interface
    init();
});