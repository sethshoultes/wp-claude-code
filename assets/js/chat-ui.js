/**
 * Modern Chat UI JavaScript
 * Provides markdown rendering, syntax highlighting, and message formatting
 */

jQuery(document).ready(function($) {
    // Check if the modern UI is enabled
    if (!claudeCodeChatUI.isEnabled) {
        return;
    }
    
    // Initialize marked.js for markdown parsing
    if (typeof marked !== 'undefined') {
        marked.setOptions({
            renderer: new marked.Renderer(),
            highlight: function(code, lang) {
                if (typeof hljs !== 'undefined') {
                    try {
                        if (lang && hljs.getLanguage(lang)) {
                            return hljs.highlight(code, { language: lang }).value;
                        } else {
                            return hljs.highlightAuto(code).value;
                        }
                    } catch (e) {
                        console.error('Highlight.js error:', e);
                    }
                }
                return code;
            },
            pedantic: false,
            gfm: true,
            breaks: true,
            smartypants: true
        });
    }
    
    // Override the default message formatting with markdown rendering
    if (typeof window.formatContent !== 'function') {
        window.formatContent = formatMarkdownContent;
    } else {
        // Store the original function and replace it
        const originalFormatContent = window.formatContent;
        window.formatContent = function(content) {
            // First apply our markdown formatting
            const formattedContent = formatMarkdownContent(content);
            
            // If the result is unchanged, fall back to original formatting
            if (formattedContent === content) {
                return originalFormatContent(content);
            }
            
            return formattedContent;
        };
    }
    
    // Replace the createMessageHtml function if it exists
    if (typeof window.createMessageHtml === 'function') {
        const originalCreateMessageHtml = window.createMessageHtml;
        window.createMessageHtml = function(type, content, toolsUsed = null) {
            const timestamp = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
            
            let html = `<div class="message ${type}">`;
            html += `<div class="message-content">${formatMarkdownContent(content)}</div>`;
            
            // Add message metadata
            html += `<div class="message-meta">`;
            html += `<span class="message-time">${timestamp}</span>`;
            
            if (type === 'assistant') {
                html += `<span class="message-from">Claude</span>`;
            } else if (type === 'user') {
                html += `<span class="message-from">${claudeCode.currentUser || 'You'}</span>`;
            }
            html += `</div>`;
            
            if (toolsUsed && toolsUsed.length > 0) {
                html += `<div class="tools-used">Tools used: ${toolsUsed.join(', ')}</div>`;
            }
            
            html += '</div>';
            
            // Add copy buttons to code blocks after the element is added to DOM
            setTimeout(function() {
                addCopyButtonsToCodeBlocks();
            }, 100);
            
            return html;
        };
    }
    
    // Function to format markdown content
    function formatMarkdownContent(content) {
        // Server-side rendering option
        if (claudeCodeChatUI.defaultMarkdownRenderer === 'server') {
            // Make an AJAX call to render the markdown
            // This is asynchronous, so we need to handle it differently
            // For now, just return the content and update it after the AJAX call
            renderMarkdownOnServer(content, function(html) {
                // Find the content with this exact markdown and update it
                updateRenderedMarkdown(content, html);
            });
            
            // Return the original content for now
            return content;
        }
        
        // Client-side rendering
        if (typeof marked !== 'undefined') {
            try {
                // First escape any HTML in the content to prevent XSS
                const escapedContent = escapeHtml(content);
                // Then process with marked
                const html = marked.parse(escapedContent);
                
                // Enhanced rendering for tables
                const enhancedHtml = enhanceRenderedHtml(html);
                
                return enhancedHtml;
            } catch (e) {
                console.error('Markdown parsing error:', e);
                return content;
            }
        }
        
        // Fallback to basic formatting if marked.js is not available
        return basicFormatting(content);
    }
    
    // Enhance rendered HTML with additional features
    function enhanceRenderedHtml(html) {
        // Add copy buttons to code blocks
        html = html.replace(/<pre><code class="language-([^"]+)">([\s\S]*?)<\/code><\/pre>/g, 
            '<div class="code-block"><pre><code class="language-$1">$2</code></pre><button class="code-copy-btn">Copy</button></div>');
        
        // If no language is specified
        html = html.replace(/<pre><code>([\s\S]*?)<\/code><\/pre>/g, 
            '<div class="code-block"><pre><code>$1</code></pre><button class="code-copy-btn">Copy</button></div>');
            
        return html;
    }
    
    // Escape HTML to prevent XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.innerText = text;
        return div.innerHTML;
    }
    
    // Basic formatting for fallback
    function basicFormatting(content) {
        // Convert markdown-style code blocks
        content = content.replace(/```(\w+)?\n([\s\S]*?)```/g, function(match, lang, code) {
            return `<div class="code-block"><pre><code class="language-${lang || ''}">${escapeHtml(code.trim())}</code></pre><button class="code-copy-btn">Copy</button></div>`;
        });
        
        // Convert inline code
        content = content.replace(/`([^`]+)`/g, '<code>$1</code>');
        
        // Convert headers
        content = content.replace(/^# (.*?)$/gm, '<h1>$1</h1>');
        content = content.replace(/^## (.*?)$/gm, '<h2>$1</h2>');
        content = content.replace(/^### (.*?)$/gm, '<h3>$1</h3>');
        
        // Convert lists
        content = content.replace(/^\* (.*?)$/gm, '<li>$1</li>');
        content = content.replace(/^\- (.*?)$/gm, '<li>$1</li>');
        
        // Wrap lists in ul tags (simplified approach)
        if (content.includes('<li>')) {
            content = '<ul>' + content + '</ul>';
            content = content.replace(/<\/li>\n<li>/g, '</li><li>');
        }
        
        // Convert line breaks
        content = content.replace(/\n/g, '<br>');
        
        return content;
    }
    
    // Server-side markdown rendering
    function renderMarkdownOnServer(content, callback) {
        $.ajax({
            url: claudeCodeChatUI.ajaxUrl,
            type: 'POST',
            data: {
                action: 'claude_code_render_markdown',
                markdown: content,
                nonce: claudeCodeChatUI.nonce
            },
            success: function(response) {
                if (response.success) {
                    callback(response.data.html);
                } else {
                    console.error('Error rendering markdown:', response.data);
                    callback(basicFormatting(content));
                }
            },
            error: function() {
                console.error('AJAX error rendering markdown');
                callback(basicFormatting(content));
            }
        });
    }
    
    // Update rendered markdown in the DOM
    function updateRenderedMarkdown(originalContent, html) {
        // Find all message contents
        $('.message-content').each(function() {
            if ($(this).text().trim() === originalContent.trim()) {
                $(this).html(html);
                
                // Add copy buttons to code blocks
                addCopyButtonsToCodeBlocks();
            }
        });
    }
    
    // Add copy buttons to code blocks
    function addCopyButtonsToCodeBlocks() {
        $('.code-block').each(function() {
            // Skip if already has a copy button
            if ($(this).find('.code-copy-btn').length > 0) {
                return;
            }
            
            const $codeBlock = $(this);
            const $copyButton = $('<button class="code-copy-btn">Copy</button>');
            
            $copyButton.on('click', function() {
                const code = $codeBlock.find('code').text();
                copyToClipboard(code);
                
                // Show copied feedback
                $(this).text('Copied!');
                setTimeout(() => $(this).text('Copy'), 2000);
            });
            
            $codeBlock.append($copyButton);
        });
    }
    
    // Copy text to clipboard
    function copyToClipboard(text) {
        // Create a temporary textarea
        const textarea = document.createElement('textarea');
        textarea.value = text;
        document.body.appendChild(textarea);
        
        // Select and copy
        textarea.select();
        document.execCommand('copy');
        
        // Clean up
        document.body.removeChild(textarea);
    }
    
    // Initialize syntax highlighting on page load
    function initSyntaxHighlighting() {
        if (typeof hljs !== 'undefined') {
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
        }
    }
    
    // Initialize UI enhancements
    function initUiEnhancements() {
        // Add copy buttons to existing code blocks
        addCopyButtonsToCodeBlocks();
        
        // Auto-resize textarea
        $('#chat-input').on('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // Initialize syntax highlighting
        initSyntaxHighlighting();
    }
    
    // Initialize the enhanced UI
    initUiEnhancements();
    
    // Override the send message function to add enhanced UI features
    if (typeof window.sendMessage === 'function') {
        const originalSendMessage = window.sendMessage;
        window.sendMessage = function() {
            // Delegate to the main sendMessage function but use enhanced UI elements
            const originalAddMessageToChat = window.addMessageToChat;
            const originalShowTypingIndicator = window.showTypingIndicator;
            const originalHideTypingIndicator = window.hideTypingIndicator;
            
            // Temporarily override UI functions with enhanced versions
            window.addMessageToChat = function(type, content, toolsUsed = null, shouldScroll = true) {
                const messagesContainer = $('#chat-messages');
                let messageHtml;
                
                if (typeof window.createMessageHtml === 'function') {
                    messageHtml = window.createMessageHtml(type, content, toolsUsed);
                } else {
                    // Fallback to basic message creation
                    messageHtml = createMessageHtml(type, content, toolsUsed);
                }
                
                messagesContainer.append(messageHtml);
                if (shouldScroll !== false) {
                    scrollToBottom();
                }
            };
            
            window.showTypingIndicator = showEnhancedTypingIndicator;
            window.hideTypingIndicator = hideTypingIndicator;
            
            // Call the original sendMessage function
            originalSendMessage();
            
            // Restore original functions
            window.addMessageToChat = originalAddMessageToChat;
            window.showTypingIndicator = originalShowTypingIndicator;
            window.hideTypingIndicator = originalHideTypingIndicator;
        };
    }
    
    // Show enhanced typing indicator
    function showEnhancedTypingIndicator() {
        const typingHtml = `
            <div class="typing-indicator" id="typing-indicator">
                <span>Claude is thinking</span>
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
    
    // Hide typing indicator
    function hideTypingIndicator() {
        $('#typing-indicator').remove();
    }
    
    // Scroll to bottom of chat
    function scrollToBottom() {
        const messagesContainer = $('#chat-messages');
        messagesContainer.scrollTop(messagesContainer[0].scrollHeight);
    }
    
    // Re-run syntax highlighting when DOM is updated
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                initSyntaxHighlighting();
                addCopyButtonsToCodeBlocks();
            }
        });
    });
    
    // Start observing chat messages for DOM changes
    observer.observe(document.getElementById('chat-messages'), {
        childList: true,
        subtree: true
    });
});