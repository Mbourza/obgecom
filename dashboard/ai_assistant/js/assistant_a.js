// ============================================
// OBGEcom AI Assistant - Enhanced JavaScript
// ============================================

// Configuration
const CONFIG = {
    apiEndpoint: './dashboard/ai_assistant/chat/ask.php',
    maxHistoryItems: 50,
    typingSpeed: 20, // milliseconds per character
    autoScrollDelay: 100,
    messageRetentionDays: 30,
    maxMessageLength: 2000,
    enableSpeech: true,
    enableMarkdown: true,
    theme: 'light', // light, dark, auto
    sessionId: generateSessionId()
};

// State management
const STATE = {
    messages: [],
    isTyping: false,
    isOnline: true,
    unreadCount: 0,
    currentSession: CONFIG.sessionId,
    lastActivity: Date.now(),
    typingQueue: [],
    userPreferences: loadPreferences()
};

// DOM Elements
let elements = {};

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    initializeElements();
    initializeEventListeners();
    loadMessageHistory();
    initializeTheme();
    checkConnection();
    setupActivityMonitoring();
    
    // Welcome message
    setTimeout(() => {
        if (STATE.messages.length === 0) {
            addSystemMessage("Hello! I'm your OBGEcom assistant. How can I help you today?");
        }
    }, 500);
});

// Generate unique session ID
function generateSessionId() {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Load user preferences from localStorage
function loadPreferences() {
    const defaults = {
        theme: 'light',
        soundEnabled: true,
        notificationsEnabled: true,
        autoScroll: true,
        compactMode: false
    };
    
    try {
        const saved = localStorage.getItem('obgecom_assistant_prefs');
        return saved ? { ...defaults, ...JSON.parse(saved) } : defaults;
    } catch (e) {
        console.error('Failed to load preferences:', e);
        return defaults;
    }
}

// Save user preferences
function savePreferences() {
    try {
        localStorage.setItem('obgecom_assistant_prefs', JSON.stringify(STATE.userPreferences));
    } catch (e) {
        console.error('Failed to save preferences:', e);
    }
}

// Initialize DOM elements
function initializeElements() {
    elements = {
        chatContainer: document.getElementById('chat-container'),
        messagesContainer: document.getElementById('messages-container'),
        messageInput: document.getElementById('message-input'),
        sendButton: document.getElementById('send-button'),
        typingIndicator: document.getElementById('typing-indicator'),
        connectionStatus: document.getElementById('connection-status'),
        clearChatBtn: document.getElementById('clear-chat-btn'),
        exportChatBtn: document.getElementById('export-chat-btn'),
        themeToggle: document.getElementById('theme-toggle'),
        settingsBtn: document.getElementById('settings-btn'),
        settingsModal: document.getElementById('settings-modal'),
        closeSettings: document.getElementById('close-settings'),
        soundToggle: document.getElementById('sound-toggle'),
        autoScrollToggle: document.getElementById('auto-scroll-toggle'),
        compactModeToggle: document.getElementById('compact-mode-toggle')
    };
    
    // Create elements if they don't exist
    if (!elements.messagesContainer) {
        elements.messagesContainer = document.createElement('div');
        elements.messagesContainer.id = 'messages-container';
        elements.messagesContainer.className = 'messages-container';
        if (elements.chatContainer) {
            elements.chatContainer.appendChild(elements.messagesContainer);
        }
    }
}

// Initialize event listeners
function initializeEventListeners() {
    // Send message on Enter (Shift+Enter for new line)
    if (elements.messageInput) {
        elements.messageInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });
        
        // Auto-resize textarea
        elements.messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        });
        
        // Update last activity
        elements.messageInput.addEventListener('input', updateLastActivity);
    }
    
    // Send button
    if (elements.sendButton) {
        elements.sendButton.addEventListener('click', sendMessage);
    }
    
    // Clear chat
    if (elements.clearChatBtn) {
        elements.clearChatBtn.addEventListener('click', clearChatWithConfirmation);
    }
    
    // Export chat
    if (elements.exportChatBtn) {
        elements.exportChatBtn.addEventListener('click', exportChat);
    }
    
    // Theme toggle
    if (elements.themeToggle) {
        elements.themeToggle.addEventListener('click', toggleTheme);
    }
    
    // Settings
    if (elements.settingsBtn) {
        elements.settingsBtn.addEventListener('click', showSettings);
    }
    
    if (elements.closeSettings) {
        elements.closeSettings.addEventListener('click', hideSettings);
    }
    
    // Close settings when clicking outside
    document.addEventListener('click', function(e) {
        if (elements.settingsModal && 
            elements.settingsModal.style.display === 'block' &&
            !elements.settingsModal.contains(e.target) &&
            e.target !== elements.settingsBtn) {
            hideSettings();
        }
    });
    
    // Settings toggles
    if (elements.soundToggle) {
        elements.soundToggle.addEventListener('change', function() {
            STATE.userPreferences.soundEnabled = this.checked;
            savePreferences();
        });
    }
    
    if (elements.autoScrollToggle) {
        elements.autoScrollToggle.addEventListener('change', function() {
            STATE.userPreferences.autoScroll = this.checked;
            savePreferences();
        });
    }
    
    if (elements.compactModeToggle) {
        elements.compactModeToggle.addEventListener('change', function() {
            STATE.userPreferences.compactMode = this.checked;
            savePreferences();
            applyCompactMode();
        });
    }
    
    // Handle offline/online events
    window.addEventListener('online', updateConnectionStatus);
    window.addEventListener('offline', updateConnectionStatus);
    
    // Handle page visibility
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            STATE.unreadCount = 0;
            updateUnreadIndicator();
        }
    });
}

// Setup activity monitoring
function setupActivityMonitoring() {
    setInterval(() => {
        const inactiveTime = Date.now() - STATE.lastActivity;
        if (inactiveTime > 300000) { // 5 minutes
            // Optional: Send inactivity notification
        }
    }, 60000); // Check every minute
}

// Update last activity timestamp
function updateLastActivity() {
    STATE.lastActivity = Date.now();
}

// Check connection status
function checkConnection() {
    updateConnectionStatus();
    setInterval(updateConnectionStatus, 30000); // Check every 30 seconds
}

// Update connection status display
function updateConnectionStatus() {
    STATE.isOnline = navigator.onLine;
    
    if (elements.connectionStatus) {
        if (STATE.isOnline) {
            elements.connectionStatus.textContent = 'Online';
            elements.connectionStatus.className = 'status-online';
        } else {
            elements.connectionStatus.textContent = 'Offline';
            elements.connectionStatus.className = 'status-offline';
        }
    }
}

// Send message to AI
async function sendMessage() {
    const input = elements.messageInput;
    if (!input) return;
    
    const message = input.value.trim();
    
    // Validate message
    if (!message) {
        showNotification('Please enter a message', 'warning');
        return;
    }
    
    if (message.length > CONFIG.maxMessageLength) {
        showNotification(`Message too long (max ${CONFIG.maxMessageLength} characters)`, 'warning');
        return;
    }
    
    // Check connection
    if (!STATE.isOnline) {
        showNotification('You are offline. Please check your connection.', 'error');
        return;
    }
    
    // Clear input and reset height
    input.value = '';
    input.style.height = 'auto';
    
    // Add user message to chat
    addMessage({
        sender: 'user',
        content: message,
        timestamp: new Date().toISOString()
    });
    
    // Show typing indicator
    showTypingIndicator();
    
    try {
        // Send to API
        const response = await fetchWithTimeout(CONFIG.apiEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Session-ID': STATE.currentSession
            },
            body: JSON.stringify({
                question: message,
                sessionId: STATE.currentSession
            })
        });
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }
        
        const data = await response.json();
        
        // Hide typing indicator
        hideTypingIndicator();
        
        if (data.success) {
            // Add AI response with typing animation
            await typeMessage({
                sender: 'assistant',
                content: data.answer,
                timestamp: new Date().toISOString(),
                cached: data.cached || false
            });
            
            // Play sound if enabled
            if (STATE.userPreferences.soundEnabled) {
                playNotificationSound();
            }
        } else {
            throw new Error(data.error || 'Unknown error');
        }
        
    } catch (error) {
        hideTypingIndicator();
        
        console.error('Error sending message:', error);
        
        // Show error message
        addMessage({
            sender: 'system',
            content: `Sorry, I encountered an error: ${error.message}. Please try again.`,
            timestamp: new Date().toISOString(),
            isError: true
        });
        
        // Show notification
        showNotification('Failed to get response. Please try again.', 'error');
    }
    
    // Save chat history
    saveMessageHistory();
}

// Fetch with timeout
async function fetchWithTimeout(url, options, timeout = 30000) {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), timeout);
    
    try {
        const response = await fetch(url, {
            ...options,
            signal: controller.signal
        });
        clearTimeout(timeoutId);
        return response;
    } catch (error) {
        clearTimeout(timeoutId);
        throw error;
    }
}

// Add message to chat
function addMessage(messageData) {
    const message = {
        id: generateMessageId(),
        ...messageData,
        timestamp: messageData.timestamp || new Date().toISOString()
    };
    
    // Add to state
    STATE.messages.push(message);
    
    // Create message element
    const messageElement = createMessageElement(message);
    
    // Add to container
    if (elements.messagesContainer) {
        elements.messagesContainer.appendChild(messageElement);
        
        // Auto-scroll if enabled
        if (STATE.userPreferences.autoScroll) {
            setTimeout(() => {
                elements.messagesContainer.scrollTop = elements.messagesContainer.scrollHeight;
            }, CONFIG.autoScrollDelay);
        }
    }
    
    // Update unread count if page is hidden
    if (document.hidden && message.sender !== 'user') {
        STATE.unreadCount++;
        updateUnreadIndicator();
    }
    
    // Trim history if needed
    if (STATE.messages.length > CONFIG.maxHistoryItems) {
        STATE.messages = STATE.messages.slice(-CONFIG.maxHistoryItems);
    }
    
    return messageElement;
}

// Type message with animation
async function typeMessage(messageData) {
    return new Promise(resolve => {
        const message = {
            ...messageData,
            id: generateMessageId(),
            content: '',
            isTyping: true
        };
        
        // Add initial message element
        const messageElement = createMessageElement(message);
        if (elements.messagesContainer) {
            elements.messagesContainer.appendChild(messageElement);
        }
        
        // Get the content element
        const contentElement = messageElement.querySelector('.message-content');
        if (!contentElement) {
            resolve();
            return;
        }
        
        // Type out the message
        const fullText = messageData.content;
        let currentIndex = 0;
        
        function typeNextCharacter() {
            if (currentIndex < fullText.length) {
                // Add next character
                contentElement.textContent = fullText.substring(0, currentIndex + 1);
                currentIndex++;
                
                // Scroll to keep visible
                if (STATE.userPreferences.autoScroll) {
                    elements.messagesContainer.scrollTop = elements.messagesContainer.scrollHeight;
                }
                
                // Schedule next character
                setTimeout(typeNextCharacter, CONFIG.typingSpeed);
            } else {
                // Update message in state
                const messageIndex = STATE.messages.findIndex(m => m.id === message.id);
                if (messageIndex !== -1) {
                    STATE.messages[messageIndex] = {
                        ...message,
                        content: fullText,
                        isTyping: false
                    };
                }
                resolve();
            }
        }
        
        // Start typing
        typeNextCharacter();
    });
}

// Generate unique message ID
function generateMessageId() {
    return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
}

// Create message element
function createMessageElement(message) {
    const div = document.createElement('div');
    div.className = `message message-${message.sender}`;
    div.dataset.messageId = message.id;
    div.dataset.sender = message.sender;
    
    if (message.isError) {
        div.classList.add('message-error');
    }
    
    if (message.cached) {
        div.classList.add('message-cached');
    }
    
    // Format timestamp
    const timestamp = new Date(message.timestamp);
    const timeString = timestamp.toLocaleTimeString([], { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
    
    // Sender name
    let senderName = 'You';
    let avatar = '👤';
    
    switch (message.sender) {
        case 'assistant':
            senderName = 'OBGEcom Assistant';
            avatar = '🤖';
            break;
        case 'system':
            senderName = 'System';
            avatar = '⚙️';
            break;
    }
    
    // Message content with markdown support
    let content = message.content;
    if (CONFIG.enableMarkdown && message.sender === 'assistant') {
        content = renderMarkdown(message.content);
    } else {
        content = escapeHtml(content);
    }
    
    div.innerHTML = `
        <div class="message-header">
            <span class="message-avatar">${avatar}</span>
            <span class="message-sender">${senderName}</span>
            <span class="message-time">${timeString}</span>
            ${message.cached ? '<span class="cache-badge" title="Cached response">♻️</span>' : ''}
        </div>
        <div class="message-content">${content}</div>
        ${message.sender === 'assistant' ? `
            <div class="message-actions">
                <button class="action-btn copy-btn" title="Copy message">
                    <span class="icon">📋</span>
                </button>
                <button class="action-btn read-btn" title="Read aloud">
                    <span class="icon">🔊</span>
                </button>
                <button class="action-btn feedback-btn" title="Provide feedback">
                    <span class="icon">👍</span>
                </button>
            </div>
        ` : ''}
    `;
    
    // Add event listeners for action buttons
    const copyBtn = div.querySelector('.copy-btn');
    if (copyBtn) {
        copyBtn.addEventListener('click', () => copyToClipboard(message.content));
    }
    
    const readBtn = div.querySelector('.read-btn');
    if (readBtn && CONFIG.enableSpeech) {
        readBtn.addEventListener('click', () => readAloud(message.content));
    }
    
    const feedbackBtn = div.querySelector('.feedback-btn');
    if (feedbackBtn) {
        feedbackBtn.addEventListener('click', () => provideFeedback(message.id));
    }
    
    return div;
}

// Add system message
function addSystemMessage(content) {
    return addMessage({
        sender: 'system',
        content: content,
        timestamp: new Date().toISOString()
    });
}

// Show typing indicator
function showTypingIndicator() {
    STATE.isTyping = true;
    
    if (elements.typingIndicator) {
        elements.typingIndicator.style.display = 'flex';
        
        if (STATE.userPreferences.autoScroll) {
            setTimeout(() => {
                if (elements.messagesContainer) {
                    elements.messagesContainer.scrollTop = elements.messagesContainer.scrollHeight;
                }
            }, CONFIG.autoScrollDelay);
        }
    }
}

// Hide typing indicator
function hideTypingIndicator() {
    STATE.isTyping = false;
    
    if (elements.typingIndicator) {
        elements.typingIndicator.style.display = 'none';
    }
}

// Load message history from localStorage
function loadMessageHistory() {
    try {
        const saved = localStorage.getItem('obgecom_chat_history');
        if (saved) {
            const history = JSON.parse(saved);
            
            // Filter old messages (optional)
            const cutoffDate = new Date();
            cutoffDate.setDate(cutoffDate.getDate() - CONFIG.messageRetentionDays);
            
            STATE.messages = history.filter(msg => {
                return new Date(msg.timestamp) > cutoffDate;
            });
            
            // Render messages
            if (elements.messagesContainer) {
                elements.messagesContainer.innerHTML = '';
                STATE.messages.forEach(msg => {
                    const element = createMessageElement(msg);
                    elements.messagesContainer.appendChild(element);
                });
                
                // Scroll to bottom
                elements.messagesContainer.scrollTop = elements.messagesContainer.scrollHeight;
            }
        }
    } catch (e) {
        console.error('Failed to load chat history:', e);
    }
}

// Save message history to localStorage
function saveMessageHistory() {
    try {
        localStorage.setItem('obgecom_chat_history', JSON.stringify(STATE.messages));
    } catch (e) {
        console.error('Failed to save chat history:', e);
    }
}

// Clear chat with confirmation
function clearChatWithConfirmation() {
    if (confirm('Are you sure you want to clear all messages? This cannot be undone.')) {
        clearChat();
    }
}

// Clear chat
function clearChat() {
    STATE.messages = [];
    
    if (elements.messagesContainer) {
        elements.messagesContainer.innerHTML = '';
    }
    
    // Add welcome message
    setTimeout(() => {
        addSystemMessage("Chat cleared. How can I help you today?");
    }, 300);
    
    // Save empty history
    saveMessageHistory();
}

// Export chat as text file
function exportChat() {
    let exportText = 'OBGEcom AI Assistant Chat Export\n';
    exportText += 'Generated: ' + new Date().toLocaleString() + '\n';
    exportText += 'Session ID: ' + STATE.currentSession + '\n';
    exportText += '='.repeat(50) + '\n\n';
    
    STATE.messages.forEach(msg => {
        const timestamp = new Date(msg.timestamp).toLocaleString();
        let sender = 'You';
        
        switch (msg.sender) {
            case 'assistant':
                sender = 'OBGEcom Assistant';
                break;
            case 'system':
                sender = 'System';
                break;
        }
        
        exportText += `[${timestamp}] ${sender}:\n`;
        exportText += msg.content + '\n';
        exportText += '-'.repeat(40) + '\n';
    });
    
    // Create and download file
    const blob = new Blob([exportText], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `obgecom_chat_${new Date().toISOString().split('T')[0]}.txt`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    
    showNotification('Chat exported successfully', 'success');
}

// Copy text to clipboard
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Copied to clipboard!', 'success');
    } catch (err) {
        console.error('Failed to copy:', err);
        showNotification('Failed to copy', 'error');
    }
}

// Read text aloud
function readAloud(text) {
    if (!CONFIG.enableSpeech) return;
    
    if ('speechSynthesis' in window) {
        // Stop any ongoing speech
        speechSynthesis.cancel();
        
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 1.0;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;
        
        // Try to use a nice voice
        const voices = speechSynthesis.getVoices();
        const preferredVoice = voices.find(voice => 
            voice.lang.includes('en') && voice.name.includes('Google')
        );
        if (preferredVoice) {
            utterance.voice = preferredVoice;
        }
        
        speechSynthesis.speak(utterance);
        showNotification('Reading aloud...', 'info');
    } else {
        showNotification('Text-to-speech not supported in this browser', 'warning');
    }
}

// Provide feedback on a message
function provideFeedback(messageId) {
    const message = STATE.messages.find(m => m.id === messageId);
    if (!message) return;
    
    const feedback = prompt('Please provide feedback for this response:\n\n' + 
                          message.content.substring(0, 200) + 
                          (message.content.length > 200 ? '...' : ''));
    
    if (feedback) {
        // Send feedback to server (you'll need to implement this endpoint)
        fetch('./chat/feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                messageId,
                feedback,
                sessionId: STATE.currentSession,
                rating: 'helpful' // You could add a rating system
            })
        }).catch(console.error);
        
        showNotification('Thank you for your feedback!', 'success');
    }
}

// Initialize theme
function initializeTheme() {
    // Check for saved theme or system preference
    if (STATE.userPreferences.theme === 'auto') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.body.classList.toggle('dark-theme', prefersDark);
    } else {
        document.body.classList.toggle('dark-theme', STATE.userPreferences.theme === 'dark');
    }
    
    // Update toggle button
    if (elements.themeToggle) {
        updateThemeToggle();
    }
}

// Toggle theme
function toggleTheme() {
    const currentTheme = STATE.userPreferences.theme;
    let newTheme;
    
    if (currentTheme === 'auto') {
        newTheme = 'dark';
    } else if (currentTheme === 'dark') {
        newTheme = 'light';
    } else {
        newTheme = 'auto';
    }
    
    STATE.userPreferences.theme = newTheme;
    savePreferences();
    
    // Apply theme
    if (newTheme === 'auto') {
        const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
        document.body.classList.toggle('dark-theme', prefersDark);
    } else {
        document.body.classList.toggle('dark-theme', newTheme === 'dark');
    }
    
    updateThemeToggle();
    showNotification(`Theme: ${newTheme.charAt(0).toUpperCase() + newTheme.slice(1)}`, 'info');
}

// Update theme toggle button
function updateThemeToggle() {
    if (!elements.themeToggle) return;
    
    const theme = STATE.userPreferences.theme;
    let icon, text;
    
    if (theme === 'auto') {
        icon = '🌓';
        text = 'Auto';
    } else if (theme === 'dark') {
        icon = '🌙';
        text = 'Dark';
    } else {
        icon = '☀️';
        text = 'Light';
    }
    
    elements.themeToggle.innerHTML = `${icon} ${text}`;
}

// Apply compact mode
function applyCompactMode() {
    if (elements.chatContainer) {
        elements.chatContainer.classList.toggle('compact-mode', STATE.userPreferences.compactMode);
    }
}

// Show settings modal
function showSettings() {
    if (elements.settingsModal) {
        // Update toggle states
        if (elements.soundToggle) {
            elements.soundToggle.checked = STATE.userPreferences.soundEnabled;
        }
        if (elements.autoScrollToggle) {
            elements.autoScrollToggle.checked = STATE.userPreferences.autoScroll;
        }
        if (elements.compactModeToggle) {
            elements.compactModeToggle.checked = STATE.userPreferences.compactMode;
        }
        
        elements.settingsModal.style.display = 'block';
    }
}

// Hide settings modal
function hideSettings() {
    if (elements.settingsModal) {
        elements.settingsModal.style.display = 'none';
    }
}

// Show notification
function showNotification(message, type = 'info') {
    // Remove existing notification
    const existing = document.querySelector('.notification-toast');
    if (existing) {
        existing.remove();
    }
    
    // Create notification
    const toast = document.createElement('div');
    toast.className = `notification-toast notification-${type}`;
    toast.textContent = message;
    
    // Add to document
    document.body.appendChild(toast);
    
    // Show with animation
    setTimeout(() => {
        toast.classList.add('show');
    }, 10);
    
    // Auto-remove after delay
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => {
            if (toast.parentNode) {
                toast.remove();
            }
        }, 300);
    }, 3000);
}

// Play notification sound
function playNotificationSound() {
    if (!STATE.userPreferences.soundEnabled) return;
    
    try {
        const audio = new Audio('data:audio/wav;base64,UklGRigAAABXQVZFZm10IBIAAAABAAEAQB8AAEAfAAABAAgAZGF0YQ');
        audio.volume = 0.3;
        audio.play().catch(() => {
            // Silent fail if audio can't play
        });
    } catch (e) {
        // Silent fail
    }
}

// Update unread indicator
function updateUnreadIndicator() {
    // Update title
    if (STATE.unreadCount > 0 && document.hidden) {
        document.title = `(${STATE.unreadCount}) OBGEcom Assistant`;
    } else {
        document.title = 'OBGEcom Assistant';
    }
    
    // Update badge if you have one in the UI
    const badge = document.querySelector('.unread-badge');
    if (badge) {
        if (STATE.unreadCount > 0) {
            badge.textContent = STATE.unreadCount;
            badge.style.display = 'inline-block';
        } else {
            badge.style.display = 'none';
        }
    }
}

// Basic markdown rendering
function renderMarkdown(text) {
    let html = escapeHtml(text);
    
    // Bold: **text** or __text__
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/__(.*?)__/g, '<strong>$1</strong>');
    
    // Italic: *text* or _text_
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/_(.*?)_/g, '<em>$1</em>');
    
    // Code: `code`
    html = html.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    // Links: [text](url)
    html = html.replace(/\[([^\]]+)\]\(([^)]+)\)/g, '<a href="$2" target="_blank" rel="noopener">$1</a>');
    
    // Lists
    html = html.replace(/^\s*[\-\*]\s+(.+)/gm, '<li>$1</li>');
    html = html.replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>');
    
    return html;
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Get message count
function getMessageCount() {
    return STATE.messages.length;
}

// Get session duration
function getSessionDuration() {
    const startTime = parseInt(STATE.currentSession.split('_')[1]);
    return Date.now() - startTime;
}

// Export utility functions
window.OBGEcomAssistant = {
    sendMessage,
    clearChat,
    exportChat,
    toggleTheme,
    getMessageCount,
    getSessionDuration,
    getState: () => ({ ...STATE }),
    getConfig: () => ({ ...CONFIG })
};