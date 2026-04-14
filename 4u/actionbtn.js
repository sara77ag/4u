window.actionbtn = {
    modal: null,
    currentProperty: null,
    currentLandlord: null,

    // ======================
    // View Property
    // ======================
    viewProperty(btn) {
        this.currentProperty = this.getPropertyData(btn);
        this.openPropertyModal();
        this.populatePropertyData();
        this.switchTab('property');
    },

    // ======================
    // View Landlord
    // ======================
    viewLandlord(btn) {
        this.currentLandlord = this.getLandlordData(btn);
        this.openLandlordModal();
        this.populateLandlordData();
        this.switchTab('landlord');
    },

    // ======================
    // View Tenant
    // ======================
    viewTenant(btn) {
        this.currentTenant = this.getTenantData(btn);
        this.openTenantModal();
        this.populateTenantData();
        this.switchTab('tenant');
    },

    // ======================
    // Get Property Data
    // ======================
    getPropertyData(btn) {
        const row = btn.closest('tr') || btn.closest('.card');
        return {
            id: row.dataset.propertyId || row.dataset.id,
            address: row.querySelector('.td-address')?.textContent.trim() || row.dataset.address || 'N/A',
            landlord: row.querySelector('.td-landlord')?.textContent.trim() || row.dataset.landlord || 'N/A',
            tenant: row.dataset.tenant || 'N/A',
            rent: row.dataset.price || 'N/A',
            type: row.dataset.type || 'N/A',
            status: row.dataset.status || 'N/A',
            startDate: row.dataset.contractStart || 'N/A',
            endDate: row.dataset.contractEnd || 'N/A',
            frequency: row.dataset.frequency || 'N/A',
            rooms: row.dataset.rooms || 'N/A',
            bathrooms: row.dataset.bathrooms || 'N/A',
            epc: row.dataset.epc || 'N/A',
            pets: row.dataset.pets ? (row.dataset.pets === "1" ? "Yes" : "No") : 'N/A',
            garden: row.dataset.garden ? (row.dataset.garden === "1" ? "Yes" : "No") : 'N/A',
            council: row.dataset.council || 'N/A',
            gasDate: row.dataset.gas || 'N/A',
            electricDate: row.dataset.electric || 'N/A',
            epcDate: row.dataset.epcDate || 'N/A',
            landlordIdExpiry: row.dataset.landlordIdExpiry || 'N/A',
            tenantIdExpiry: row.dataset.tenantIdExpiry || 'N/A'
        };
    },

    // ======================
    // Get Landlord Data
    // ======================
    getLandlordData(btn) {
        const container = btn.closest('.card') || btn.closest('tr');
        return {
            name: container.querySelector('.card-name')?.textContent.trim() || container.dataset.name || 'N/A',
            phone: container.dataset.phone || 'N/A',
            email: container.dataset.email || 'N/A',
            idtype: row.dataset.idtype || 'N/A',
            idnumber: container.dataset.idnumber || 'N/A',
            idexpiry: container.dataset.idexpiry || 'N/A',
            address: container.dataset.address || 'N/A',
            contact: container.dataset.contact || 'N/A',
            properties: container.dataset.properties || 0
        };
    },

    // ======================
    // Get Tenant Data
    // ======================
    getTenantData(btn) {
        const row = btn.closest('tr') || btn.closest('.card');
        return {
            id: row.dataset.tenantId || 'N/A',
            name: row.dataset.name || 'N/A',
            phone: row.dataset.phone || 'N/A',
            email: row.dataset.email || 'N/A',
            address: row.dataset.address || 'N/A',
            idtype: row.dataset.idtype || 'N/A',
            idnumber: row.dataset.idnumber || 'N/A',
            idexpiry: row.dataset.idexpiry || 'N/A',
            contact: row.dataset.contact || 'N/A',
            contractStart: row.dataset.contractStart || 'N/A',
            contractEnd: row.dataset.contractEnd || 'N/A',
            frequency: row.dataset.frequency || 'N/A',
            landlord: row.dataset.landlord || 'N/A',
            rent: row.dataset.rent || 'N/A',
            status: row.dataset.status || 'N/A'
            };
    },

    // ======================
    // Open Modals
    // ======================
    openPropertyModal() {
        this.modal = document.getElementById('viewPropertyModal');
        this.showModal();
    },

    openLandlordModal() {
        this.modal = document.getElementById('viewLandlordModal');
        this.showModal();
    },

    openTenantModal() {
        this.modal = document.getElementById('viewTenantModal');
        this.showModal();
    },

    showModal() {
        if (this.modal) {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            // Close on overlay click
            this.modal.onclick = (e) => {
            if (e.target === this.modal) this.closeModal();
            };
        }
    },

    // ======================
    // Close Modal
    // ======================
    closeModal() {
        if (this.modal) {
            this.modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            this.modal = null;
            this.currentProperty = null;
            this.currentLandlord = null;
            this.currentTenant = null;
        }
    },

    // ======================
    // Populate Property Data
    // ======================
    populatePropertyData() {
        const data = this.currentProperty;
        if (!data) return;

        document.getElementById('propertyTitle').textContent = data.address;
        document.getElementById('p-property-landlord').textContent = data.landlord;

        const statusEl = document.getElementById('p-property-status');
        statusEl.textContent = data.status.charAt(0).toUpperCase() + data.status.slice(1);
        statusEl.className = `status-badge status-${data.status}`;

        document.getElementById('p-property-type').textContent = data.type;
        document.getElementById('p-property-tenant').textContent = data.tenant;
        document.getElementById('p-property-rooms').textContent = data.rooms || 'N/A';
        document.getElementById('p-property-bathrooms').textContent = data.bathrooms || 'N/A';
        document.getElementById('p-property-epc').textContent = data.epc || 'N/A';
        document.getElementById('p-property-pets').textContent = data.pets === "Yes" || data.pets === "1" ? "Yes" : "No";
        document.getElementById('p-property-garden').textContent = data.garden === "Yes" || data.garden === "1" ? "Yes" : "No";
        document.getElementById('p-property-council').textContent = data.council || 'N/A';

        document.getElementById('p-contract-landlord').textContent = data.landlord;
        document.getElementById('p-contract-tenant').textContent = data.tenant;
        document.getElementById('p-contract-rent').textContent = `£${parseInt(data.rent || 0).toLocaleString()}`;
        document.getElementById('p-contract-frequency').textContent = data.frequency || 'N/A';
        document.getElementById('p-contract-start').textContent = data.startDate || 'N/A';
        document.getElementById('p-contract-end').textContent = data.endDate || 'N/A';

        document.getElementById('p-date-landlord-id').textContent = data.landlordIdExpiry || 'N/A';
        document.getElementById('p-date-tenant-id').textContent = data.tenantIdExpiry || 'N/A';
        document.getElementById('p-date-contract-end').textContent = data.endDate || 'N/A';
        document.getElementById('p-date-gas').textContent = data.gasDate || 'N/A';
        document.getElementById('p-date-electric').textContent = data.electricDate || 'N/A';
        document.getElementById('p-date-epc').textContent = data.epcDate || 'N/A';

    
        // ======================
        // FETCH NOTES
        // ======================
        fetch(`notes.php?property_id=${data.id}`)
            .then(res => res.json())
            .then(notes => {
                const notesList = document.getElementById('p-notesList');
                notesList.innerHTML = '';

                notes.forEach(n => {
                    notesList.insertAdjacentHTML('afterbegin', `
                        <div class="note-item">
                            <div class="note-text">${n.note_text}</div>
                            <div class="note-timestamp">
                                <i class="fa-solid fa-clock"></i>
                                ${n.created_at} - ${n.created_by ?? 'Unknown'}
                            </div>
                        </div>
                    `);
                });
            });

    // ==========================
    // Fetch Files 
    // ==========================
    fetch(`get_files.php?property_id=${data.id}`)
        .then(res => res.json())
        .then(files => {
            const filesList = document.querySelector('#p-tab-files .files-list');
            filesList.innerHTML = '';
            files.forEach(f => {
                filesList.insertAdjacentHTML('beforeend', `
                    <div class="file-item">
                        <i class="fa-solid fa-file-pdf file-icon"></i>
                        <span class="file-name">${f.file_name}</span>
                        <button class="file-open-btn" onclick="window.open('${f.file_path}', '_blank')">Open</button>
                    </div>
                `);
            });
        });

    // ==========================
    // Calculate Payment Due Date
    // ==========================
    if (data.startDate && data.frequency) {
        const start = new Date(data.startDate);
        const freq = data.frequency.toLowerCase();
        const today = new Date();
        let due = new Date(start);

        while (due < today) {
            if (freq === 'monthly') due.setMonth(due.getMonth() + 1);
            else if (freq === 'weekly') due.setDate(due.getDate() + 7);
            else if (freq === 'biweekly') due.setDate(due.getDate() + 14);
            else if (freq === 'quarterly') due.setMonth(due.getMonth() + 3);
        }
        document.getElementById('p-contract-duedate').textContent = due.toISOString().split('T')[0];
    } else {
        document.getElementById('p-contract-duedate').textContent = 'N/A';
    }
},

    // ======================
    // Populate Landlord Data
    // ======================
    populateLandlordData() {
        const data = this.currentLandlord;
        if (!data) return;

        document.getElementById('landlordName').textContent = data.name;
        document.getElementById('l-landlord-name').textContent = data.name;
        document.getElementById('l-landlord-phone').textContent = data.phone;
        document.getElementById('l-landlord-email').textContent = data.email;
        document.getElementById('l-landlord-idtype').textContent = data.idtype;
        document.getElementById('l-landlord-idnumber').textContent = data.idnumber;
        document.getElementById('l-landlord-idexpiry').textContent = data.idexpiry;
        document.getElementById('l-landlord-address').textContent = data.address;
        document.getElementById('l-landlord-contact').textContent = data.contact;
    },

    // ======================
    // Populate Tenant Data
    // ======================
    populateTenantData() {
        const data = this.currentTenant;
        if (!data) return;

        document.getElementById('tenantName').textContent = data.name;
        document.getElementById('t-tenant-name').textContent = data.name;
        document.getElementById('t-tenant-phone').textContent = data.phone;
        document.getElementById('t-tenant-email').textContent = data.email;
        document.getElementById('t-tenant-idtype').textContent = data.idtype;
        document.getElementById('t-tenant-idnumber').textContent = data.idnumber;
        document.getElementById('l-tenant-idexpiry').textContent = data.idexpiry;
        document.getElementById('l-tenant-address').textContent = data.address;
        document.getElementById('t-tenant-contact').textContent = data.contact;
        // current tenancy
        document.getElementById('t-currentTenancy-address').textContent = data.address;
        document.getElementById('t-currentTenancy-landlord').textContent = data.landlord;
        document.getElementById('t-currentTenancy-contractStart').textContent = data.contractStart;
        document.getElementById('t-currentTenancy-contractEnd').textContent = data.contractEnd;
        document.getElementById('t-currentTenancy-rent').textContent = `£${parseInt(data.rent || 0).toLocaleString()}`;
        document.getElementById('t-currentTenancy-status').textContent = data.status;
    },

    // ======================
    // Switch Tabs
    // ======================
    switchTab(tabId, event) {
    if (!this.modal) return;

    const tabs = this.modal.querySelectorAll('.tab-content');
    const btns = this.modal.querySelectorAll('.tab-btn');

    tabs.forEach(tab => tab.classList.remove('active'));
    btns.forEach(btn => btn.classList.remove('active'));

    let prefix = 'l-tab-';

    if (this.modal.id.startsWith('viewProperty')) prefix = 'p-tab-';
    else if (this.modal.id.startsWith('viewTenant')) prefix = 't-tab-';

    const targetTab = this.modal.querySelector(`#${prefix}${tabId}`);
    if (targetTab) targetTab.classList.add('active');

    if (event) event.target.classList.add('active');
},


    // ======================
    // ADD NOTE
    // ======================
    addNote() {
        if (!this.modal) return;

        const textarea = document.getElementById('p-noteTextarea');
        const text = textarea.value.trim();
        if (!text) return;

        fetch('notes.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `property_id=${this.currentProperty.id}&note_text=${encodeURIComponent(text)}`
        })
        .then(res => res.json())
        .then(data => {

            const notesList = document.getElementById('p-notesList');

            notesList.insertAdjacentHTML('afterbegin', `
                <div class="note-item">
                    <div class="note-text">${this.escapeHtml(text)}</div>
                    <div class="note-timestamp">
                        <i class="fa-solid fa-clock"></i>
                        ${data.created_at} - ${data.created_by ?? 'Unknown'}
                    </div>
                </div>
            `);

            textarea.value = '';
        });
    }
};

// ======================
// Tab Switching Delegation
// ======================
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.tab-btn');
    if (btn) {
        const tabId = btn.dataset.tab;
        actionbtn.switchTab(tabId, e);
    }
});

// ======================
// File open 
// ======================
function openFile(fileType) {
    alert(`Opening ${fileType.toUpperCase()} file... (Implement PDF viewer)`);
}

