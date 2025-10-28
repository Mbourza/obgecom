<style>
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        animation: fadeIn 0.2s ease;
    }

    .modal.active {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    @keyframes slideUp {
        from { 
            opacity: 0;
            transform: translateY(20px);
        }
        to { 
            opacity: 1;
            transform: translateY(0);
        }
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        width: 90%;
        max-width: 700px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        animation: slideUp 0.3s ease;
    }

    .modal-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #f8f9fa;
        border-radius: 12px 12px 0 0;
    }

    .modal-title {
        font-size: 18px;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 28px;
        color: #666;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .close-modal:hover {
        background: #e0e0e0;
        color: #333;
    }

    .modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }

    .form-group {
        margin-bottom: 16px;
    }

    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #666;
        margin-bottom: 8px;
    }

    .email-input-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 8px;
        border: 1px solid #d0d0d0;
        border-radius: 6px;
        min-height: 44px;
        background: white;
        cursor: text;
        transition: all 0.2s ease;
    }

    .email-input-wrapper:focus-within {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .email-chip {
        display: flex;
        align-items: center;
        gap: 6px;
        background: #e8eafd;
        color: #333;
        padding: 4px 8px;
        border-radius: 16px;
        font-size: 14px;
        animation: chipIn 0.2s ease;
    }

    @keyframes chipIn {
        from {
            opacity: 0;
            transform: scale(0.8);
        }
        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .email-chip .remove-chip {
        background: none;
        border: none;
        color: #666;
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .email-chip .remove-chip:hover {
        background: rgba(0,0,0,0.1);
        color: #333;
    }

    .email-input {
        border: none;
        outline: none;
        flex: 1;
        min-width: 150px;
        font-size: 14px;
        padding: 4px;
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #d0d0d0;
        border-radius: 6px;
        font-size: 14px;
        transition: all 0.2s ease;
        font-family: inherit;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .email-body {
        min-height: 200px;
        resize: vertical;
    }

    .cc-bcc-toggle {
        display: flex;
        gap: 15px;
        margin-bottom: 12px;
    }

    .toggle-btn {
        background: none;
        border: none;
        color: #667eea;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        padding: 4px 8px;
        border-radius: 4px;
        transition: all 0.2s ease;
    }

    .toggle-btn:hover {
        background: #f0f2ff;
    }

    .toggle-btn.active {
        background: #e8eafd;
    }

    .hidden {
        display: none !important;
    }

    .attachments-section {
        margin-top: 16px;
    }

    .file-input-wrapper {
        display: none;
    }

    .file-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin-top: 10px;
    }

    .file-item {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #f5f5f5;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 13px;
        max-width: 250px;
    }

    .file-item i {
        font-size: 18px;
        color: #667eea;
    }

    .file-name {
        flex: 1;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .file-size {
        color: #999;
        font-size: 12px;
    }

    .remove-file {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        transition: all 0.2s ease;
    }

    .remove-file:hover {
        background: #e0e0e0;
        color: #333;
    }

    .modal-footer {
        padding: 16px 20px;
        border-top: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafafa;
        border-radius: 0 0 12px 12px;
    }

    .footer-left {
        display: flex;
        gap: 10px;
    }

    .footer-right {
        display: flex;
        gap: 10px;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
    }

    .btn-icon {
        background: white;
        color: #666;
        border: 1px solid #d0d0d0;
        padding: 10px;
        width: 40px;
        height: 40px;
        justify-content: center;
    }

    .btn-icon:hover {
        background: #f5f5f5;
        border-color: #999;
    }

    .btn-outline {
        background: white;
        color: #666;
        border: 1px solid #d0d0d0;
    }

    .btn-outline:hover {
        background: #f5f5f5;
        border-color: #999;
    }

    .btn-primary {
        background: #667eea;
        color: white;
    }

    .btn-primary:hover {
        background: #5568d3;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }

    .loading-spinner {
        width: 16px;
        height: 16px;
        border: 2px solid #ffffff;
        border-top-color: transparent;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }

    @keyframes spin {
        to { transform: rotate(360deg); }
    }

    @media (max-width: 640px) {
        .modal-content {
            width: 95%;
            max-height: 95vh;
        }

        .footer-left, .footer-right {
            flex-direction: column;
        }

        .modal-footer {
            flex-direction: column;
            gap: 10px;
        }
    }
</style>

<div id="composeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">
                <i class="bi bi-pencil-square"></i>
                Nouvel Email
            </h3>
            <button class="close-modal" onclick="closeModal('composeModal')">&times;</button>
        </div>
        
        <form id="composeForm" onsubmit="handleEmailSubmit(event)">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Destinataire</label>
                    <div class="email-input-wrapper" onclick="focusInput('toInput')">
                        <div id="toChips"></div>
                        <input type="text" 
                                class="email-input" 
                                id="toInput" 
                                placeholder="Ajouter des destinataires"
                                onkeydown="handleEmailInput(event, 'to')"
                                onblur="addEmailOnBlur(event, 'to')">
                    </div>
                </div>

                <div class="form-group hidden" id="ccGroup">
                    <label class="form-label">CC</label>
                    <div class="email-input-wrapper" onclick="focusInput('ccInput')">
                        <div id="ccChips"></div>
                        <input type="text" 
                                class="email-input" 
                                id="ccInput" 
                                placeholder="Ajouter des destinataires en copie"
                                onkeydown="handleEmailInput(event, 'cc')"
                                onblur="addEmailOnBlur(event, 'cc')">
                    </div>
                </div>

                <div class="form-group hidden" id="bccGroup">
                    <label class="form-label">BCC</label>
                    <div class="email-input-wrapper" onclick="focusInput('bccInput')">
                        <div id="bccChips"></div>
                        <input type="text" 
                                class="email-input" 
                                id="bccInput" 
                                placeholder="Ajouter des destinataires en copie cachée"
                                onkeydown="handleEmailInput(event, 'bcc')"
                                onblur="addEmailOnBlur(event, 'bcc')">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Sujet</label>
                    <input type="text" 
                            id="subject" 
                            class="form-control" 
                            placeholder="Sujet de l'email"
                            required>
                </div>

                <div class="form-group">
                    <label class="form-label">Message</label>
                    <textarea id="message" 
                                class="form-control email-body" 
                                placeholder="Votre message..."
                                required></textarea>
                </div>

                <div class="attachments-section">
                    <input type="file" 
                            id="fileInput" 
                            class="file-input-wrapper" 
                            multiple
                            onchange="handleFileSelect(event)">
                    <div id="fileList" class="file-list"></div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-left">
                    <button type="button" class="btn btn-icon" onclick="document.getElementById('fileInput').click()" title="Joindre un fichier">
                        <i class="bi bi-paperclip"></i>
                    </button>
                </div>
                <div class="footer-right">
                    <button type="button" class="btn btn-outline" onclick="closeModal('composeModal')">
                        <i class="bi bi-x-circle"></i>
                        Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" id="sendBtn">
                        <span id="btnText">
                            <i class="bi bi-send"></i>
                            Envoyer
                        </span>
                        <span class="loading-spinner" id="btnSpinner" style="display: none;"></span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>

    const emails = { to: [], cc: [], bcc: [] };
    const files = [];

    function resetForm() {
        emails.to = [];
        emails.cc = [];
        emails.bcc = [];
        files.length = 0;
        document.getElementById('composeForm').reset();
        document.getElementById('toChips').innerHTML = '';
        document.getElementById('ccChips').innerHTML = '';
        document.getElementById('bccChips').innerHTML = '';
        document.getElementById('fileList').innerHTML = '';
        document.getElementById('ccGroup').classList.add('hidden');
        document.getElementById('bccGroup').classList.add('hidden');
    }

    function toggleField(field) {
        const group = document.getElementById(field + 'Group');
        group.classList.toggle('hidden');
        if (!group.classList.contains('hidden')) {
            document.getElementById(field + 'Input').focus();
        }
    }

    function focusInput(inputId) {
        document.getElementById(inputId).focus();
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function addEmail(type, email) {
        email = email.trim();
        if (!email) return;
        
        if (!validateEmail(email)) {
            alert('Adresse email invalide: ' + email);
            return;
        }

        if (emails[type].includes(email)) {
            return;
        }

        emails[type].push(email);
        renderChips(type);
        document.getElementById(type + 'Input').value = '';
    }

    function removeEmail(type, email) {
        emails[type] = emails[type].filter(e => e !== email);
        renderChips(type);
    }

    function renderChips(type) {
        const container = document.getElementById(type + 'Chips');
        container.innerHTML = emails[type].map(email => `
            <div class="email-chip">
                <span>${email}</span>
                <button type="button" class="remove-chip" onclick="removeEmail('${type}', '${email}')">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
    }

    function handleEmailInput(e, type) {
        const input = e.target;
        const value = input.value;

        if (e.key === 'Enter' || e.key === ',' || e.key === ' ') {
            e.preventDefault();
            addEmail(type, value);
        } else if (e.key === 'Backspace' && !value && emails[type].length > 0) {
            emails[type].pop();
            renderChips(type);
        }
    }

    function addEmailOnBlur(e, type) {
        const value = e.target.value.trim();
        if (value) {
            addEmail(type, value);
        }
    }

    function handleFileSelect(e) {
        const selectedFiles = Array.from(e.target.files);
        selectedFiles.forEach(file => {
            if (!files.some(f => f.name === file.name && f.size === file.size)) {
                files.push(file);
            }
        });
        renderFiles();
    }

    function removeFile(index) {
        files.splice(index, 1);
        renderFiles();
        document.getElementById('fileInput').value = '';
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    function getFileIcon(filename) {
        const ext = filename.split('.').pop().toLowerCase();
        const icons = {
            pdf: 'bi-file-earmark-pdf',
            doc: 'bi-file-earmark-word',
            docx: 'bi-file-earmark-word',
            xls: 'bi-file-earmark-excel',
            xlsx: 'bi-file-earmark-excel',
            ppt: 'bi-file-earmark-ppt',
            pptx: 'bi-file-earmark-ppt',
            jpg: 'bi-file-earmark-image',
            jpeg: 'bi-file-earmark-image',
            png: 'bi-file-earmark-image',
            gif: 'bi-file-earmark-image',
            zip: 'bi-file-earmark-zip',
            txt: 'bi-file-earmark-text',
            mp4: 'bi-file-earmark-play',
            mp3: 'bi-file-earmark-music'
        };
        return icons[ext] || 'bi-file-earmark';
    }

    function renderFiles() {
        const container = document.getElementById('fileList');
        container.innerHTML = files.map((file, index) => `
            <div class="file-item">
                <i class="bi ${getFileIcon(file.name)}"></i>
                <span class="file-name" title="${file.name}">${file.name}</span>
                <span class="file-size">${formatFileSize(file.size)}</span>
                <button type="button" class="remove-file" onclick="removeFile(${index})">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `).join('');
    }

    function handleEmailSubmit(e) {
        e.preventDefault();

        if (emails.to.length === 0) {
            alert('Veuillez ajouter au moins un destinataire');
            return;
        }

        const sendBtn = document.getElementById('sendBtn');
        const btnText = document.getElementById('btnText');
        const btnSpinner = document.getElementById('btnSpinner');

        sendBtn.disabled = true;
        btnText.style.display = 'none';
        btnSpinner.style.display = 'block';

        const formData = {
            to: emails.to,
            cc: emails.cc,
            bcc: emails.bcc,
            subject: document.getElementById('subject').value,
            message: document.getElementById('message').value,
            files: files.map(f => ({ name: f.name, size: f.size, type: f.type }))
        };

        console.log('Email data:', formData);

        setTimeout(() => {
            alert('Email envoyé avec succès!');
            closeModal();
            sendBtn.disabled = false;
            btnText.style.display = 'flex';
            btnSpinner.style.display = 'none';
        }, 2000);
    }

    document.getElementById('composeModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>