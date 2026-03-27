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
    // Get Property Data
    // ======================
    getPropertyData(btn) {
        const row = btn.closest('tr') || btn.closest('.card');
        return {
            id: row.dataset.propertyId || row.dataset.id,
            address: row.querySelector('.td-address')?.textContent.trim() || row.dataset.address || 'N/A',
            landlord: row.querySelector('.td-landlord')?.textContent.trim() || row.dataset.landlord || 'N/A',
            tenant: row.dataset.tenant || '-',
            rent: row.dataset.price,
            type: row.dataset.type,
            status: row.dataset.status,
            startDate: row.dataset.contractStart,
            endDate: row.dataset.contractEnd,
            frequency: row.dataset.frequency,
            rooms: row.dataset.rooms,
            bathrooms: row.dataset.bathrooms,
            epc: row.dataset.epc,
            pets: row.dataset.pets === "1" ? "Yes" : "No",
            balcony: row.dataset.balcony === "1" ? "Yes" : "No",
            garden: row.dataset.garden === "1" ? "Yes" : "No",
            council: row.dataset.council,
            gasDate: row.dataset.gas,
            electricDate: row.dataset.electric,
            epcDate: row.dataset.epcDate,
            landlordIdExpiry: row.dataset.landlordIdExpiry,
            tenantIdExpiry: row.dataset.tenantIdExpiry
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
            idtype: container.dataset.idtype || 'Passport',
            idnumber: container.dataset.idnumber || 'N/A',
            idexpiry: container.dataset.idexpiry || 'N/A',
            address: container.dataset.address || 'N/A',
            contact: container.dataset.contact || 'N/A',
            properties: container.dataset.properties || 0
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

    showModal() {
        if (this.modal) {
            this.modal.classList.add('active');
            document.body.style.overflow = 'hidden';
            // Close on overlay click
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) this.closeModal();
            });
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
        document.getElementById('p-contract-rent').textContent = `£${parseInt(data.rent || 0).toLocaleString()}`;
        document.getElementById('p-contract-frequency').textContent = data.frequency || 'N/A';
        document.getElementById('p-contract-start').textContent = data.startDate || 'N/A';
        document.getElementById('p-contract-end').textContent = data.endDate || 'N/A';
        document.getElementById('p-property-rooms').textContent = data.rooms || 'N/A';
        document.getElementById('p-property-bathrooms').textContent = data.bathrooms || 'N/A';
        document.getElementById('p-property-epc').textContent = data.epc || 'N/A';
        document.getElementById('p-property-pets').textContent = data.pets || 'N/A';
        document.getElementById('p-property-balcony').textContent = data.balcony || 'N/A';
        document.getElementById('p-property-garden').textContent = data.garden || 'N/A';
        document.getElementById('p-property-council').textContent = data.council || 'N/A';
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
    // Switch Tabs
    // ======================
    switchTab(tabId, event) {
        if (!this.modal) return;

        const tabs = this.modal.querySelectorAll('.tab-content');
        const btns = this.modal.querySelectorAll('.tab-btn');

        tabs.forEach(tab => tab.classList.remove('active'));
        btns.forEach(btn => btn.classList.remove('active'));

        const targetTab = this.modal.querySelector(`#${this.modal.id.startsWith('viewProperty') ? 'p-tab-' : 'l-tab-'}${tabId}`);
        if (targetTab) targetTab.classList.add('active');
        if (event) event.target.classList.add('active');
    },

    // ======================
    // Add Note
    // ======================
    addNote() {
        if (!this.modal) return;

        const isProperty = this.modal.id === 'viewPropertyModal';
        const textarea = document.getElementById(isProperty ? 'p-noteTextarea' : 'l-noteTextarea');
        const notesList = document.getElementById(isProperty ? 'p-notesList' : 'l-note-notesList');
        const text = textarea.value.trim();
        if (!text) return;

        const timestamp = new Date().toLocaleString();
        const username = 'Current User'; // Replace dynamically if needed

        const noteHtml = `
            <div class="note-item">
                <div class="note-text">${this.escapeHtml(text)}</div>
                <div class="note-timestamp">
                    <i class="fa-solid fa-clock"></i>
                    ${timestamp} - ${username}
                </div>
            </div>
        `;
        notesList.insertAdjacentHTML('afterbegin', noteHtml);
        textarea.value = '';
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
// File open placeholder
// ======================
function openFile(fileType) {
    alert(`Opening ${fileType.toUpperCase()} file... (Implement PDF viewer)`);
}

