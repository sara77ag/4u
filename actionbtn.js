// ============================================================
//  Supports: Property | Landlord | Tenant
//  Mode:     view | edit
// ============================================================

window.actionbtn = (() => {

  // ─────────────────────────────────────────────
  // STATE
  // ─────────────────────────────────────────────
  const state = {
    modal:      null,
    entity:     null,   // current data object
    entityType: null,   // 'property' | 'landlord' | 'tenant'
    mode:       'view', // 'view' | 'edit'
    formState:  {},  
  };


  // ─────────────────────────────────────────────
  // FIELD SCHEMAS
  // Each field: { id, label, key, type, tab, options? }
  //   type: 'text' | 'number' | 'date' | 'select' | 'computed' | 'badge'
  //   tab:  which tab panel the field lives in
  // ─────────────────────────────────────────────

  const SCHEMAS = {

    property: [
      // ── Property tab ──────────────────────────
      { id: 'p-property-landlord',   key: 'landlord',        type: 'text',   tab: 'property',  editable: true  },
      { id: 'p-property-status',     key: 'status',          type: 'badge',  tab: 'property',  editable: false },
      { id: 'p-property-price',      key: 'price',           type: 'number', tab: 'property',  editable: true,  prefix: '£', format: 'currency' },
      { id: 'p-property-type',       key: 'type',            type: 'select', tab: 'property',  editable: true,  options: ['Flat','House','Apartment','Bungalow'] },
      { id: 'p-property-tenant',     key: 'tenant',          type: 'text',   tab: 'property',  editable: true  },
      { id: 'p-property-rooms',      key: 'rooms',           type: 'number', tab: 'property',  editable: true,  fallback: 'N/A' },
      { id: 'p-property-bathrooms',  key: 'bathrooms',       type: 'number', tab: 'property',  editable: true,  fallback: 'N/A' },
      { id: 'p-property-epc',        key: 'epc',             type: 'text',   tab: 'property',  editable: true,  fallback: 'N/A' },
      { id: 'p-property-pets',       key: 'pets',            type: 'select', tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'p-property-garden',     key: 'garden',          type: 'select', tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'p-property-council',    key: 'council',         type: 'text',   tab: 'property',  editable: true,  fallback: 'N/A' },

      // ── Contract tab ──────────────────────────
      { id: 'p-contract-landlord',   key: 'landlord',        type: 'text',   tab: 'contract',  editable: false },
      { id: 'p-contract-tenant',     key: 'tenant',          type: 'text',   tab: 'contract',  editable: false },
      { id: 'p-contract-rent',       key: 'rent',            type: 'number', tab: 'contract',  editable: true,  prefix: '£', format: 'currency', fallback: 'N/A' },
      { id: 'p-contract-frequency',  key: 'frequency',       type: 'select', tab: 'contract',  editable: true,  options: ['Monthly','Weekly','Biweekly','Quarterly'], fallback: 'N/A' },
      { id: 'p-contract-start',      key: 'startDate',       type: 'date',   tab: 'contract',  editable: true,  fallback: 'N/A' },
      { id: 'p-contract-end',        key: 'endDate',         type: 'date',   tab: 'contract',  editable: true,  fallback: 'N/A' },
      { id: 'p-contract-duedate',    key: '_dueDate',        type: 'computed', tab: 'contract', editable: false },

      // ── Dates tab ─────────────────────────────
      { id: 'p-date-landlord-id',    key: 'landlordIdExpiry',type: 'date',   tab: 'dates',     editable: true,  fallback: 'N/A' },
      { id: 'p-date-tenant-id',      key: 'tenantIdExpiry',  type: 'date',   tab: 'dates',     editable: true,  fallback: 'N/A' },
      { id: 'p-date-contract-end',   key: 'endDate',         type: 'date',   tab: 'dates',     editable: false, fallback: 'N/A' },
      { id: 'p-date-gas',            key: 'gasDate',         type: 'date',   tab: 'dates',     editable: true,  fallback: 'N/A' },
      { id: 'p-date-electric',       key: 'electricDate',    type: 'date',   tab: 'dates',     editable: true,  fallback: 'N/A' },
      { id: 'p-date-epc',            key: 'epcDate',         type: 'date',   tab: 'dates',     editable: true,  fallback: 'N/A' },
    ],

    landlord: [
      { id: 'l-landlord-name',     key: 'name',       type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-phone',    key: 'phone',      type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-email',    key: 'email',      type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-idtype',   key: 'idtype',     type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-idnumber', key: 'idnumber',   type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-idexpiry', key: 'idexpiry',   type: 'date', tab: 'landlord', editable: true  },
      { id: 'l-landlord-address',  key: 'address',    type: 'text', tab: 'landlord', editable: true  },
      { id: 'l-landlord-contact',  key: 'contact',    type: 'text', tab: 'landlord', editable: true  },
    ],

    tenant: [
      { id: 't-tenant-name',                   key: 'name',          type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-tenant-phone',                  key: 'phone',         type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-tenant-email',                  key: 'email',         type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-tenant-idtype',                 key: 'idtype',        type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-tenant-idnumber',               key: 'idnumber',      type: 'text', tab: 'tenant',   editable: true  },
      { id: 'l-tenant-idexpiry',               key: 'idexpiry',      type: 'date', tab: 'tenant',   editable: true  },
      { id: 'l-tenant-address',                key: 'address',       type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-tenant-contact',                key: 'contact',       type: 'text', tab: 'tenant',   editable: true  },
      { id: 't-currentTenancy-address',        key: 'address',       type: 'text', tab: 'tenancy',  editable: false },
      { id: 't-currentTenancy-landlord',       key: 'landlord',      type: 'text', tab: 'tenancy',  editable: false },
      { id: 't-currentTenancy-contractStart',  key: 'contractStart', type: 'date', tab: 'tenancy',  editable: false },
      { id: 't-currentTenancy-contractEnd',    key: 'contractEnd',   type: 'date', tab: 'tenancy',  editable: false },
      { id: 't-currentTenancy-rent',           key: 'rent',          type: 'number', tab: 'tenancy', editable: false, prefix: '£', format: 'currency' },
      { id: 't-currentTenancy-status',         key: 'status',        type: 'text', tab: 'tenancy',  editable: false },
    ],
  };


  // ─────────────────────────────────────────────
  // SAVE PAYLOAD MAP
  // Defines which schema keys map to which payload keys for each entity.
  // Only fields explicitly listed here are included in the POST body.
  // ─────────────────────────────────────────────
  const SAVE_MAP = {
    property: {
      id:          '_id',
      landlord:    'p-property-landlord',
      type:        'p-property-type',
      price:       'p-property-price',
      tenant:      'p-property-tenant',
      rooms:       'p-property-rooms',
      bathrooms:   'p-property-bathrooms',
      epc:         'p-property-epc',
      pets:        'p-property-pets',
      garden:      'p-property-garden',
      council:     'p-property-council',
      rent:        'p-contract-rent',
      frequency:   'p-contract-frequency',
      start:       'p-contract-start',
      end:         'p-contract-end',
      landlordId:  'p-date-landlord-id',
      tenantId:    'p-date-tenant-id',
      gas:         'p-date-gas',
      electric:    'p-date-electric',
      epcDate:     'p-date-epc',
    },
  };


  // ─────────────────────────────────────────────
  // DATA EXTRACTORS
  // ─────────────────────────────────────────────

  function extractPropertyData(btn) {
    const row = btn.closest('tr') || btn.closest('.card');
    return {
      _id:              row.dataset.propertyId || row.dataset.id,
      address:          row.querySelector('.td-address')?.textContent.trim() || row.dataset.address || '',
      landlord:         row.querySelector('.td-landlord')?.textContent.trim() || row.dataset.landlord || '',
      tenant:           row.dataset.tenant || '',
      rent:             row.dataset.contractRent || '',
      price:            row.dataset.price || '',
      type:             row.dataset.type || '',
      status:           row.dataset.status || '',
      startDate:        row.dataset.contractStart || '',
      endDate:          row.dataset.contractEnd || '',
      frequency:        row.dataset.frequency || '',
      rooms:            row.dataset.rooms || '',
      bathrooms:        row.dataset.bathrooms || '',
      epc:              row.dataset.epc || '',
      pets:             row.dataset.pets ? (row.dataset.pets === '1' ? 'Yes' : 'No') : '',
      garden:           row.dataset.garden ? (row.dataset.garden === '1' ? 'Yes' : 'No') : '',
      council:          row.dataset.council || '',
      gasDate:          row.dataset.gas || '',
      electricDate:     row.dataset.electric || '',
      epcDate:          row.dataset.epcDate || '',
      landlordIdExpiry: row.dataset.landlordIdExpiry || '',
      tenantIdExpiry:   row.dataset.tenantIdExpiry || '',
    };
  }

  function extractLandlordData(btn) {
    const c = btn.closest('.card') || btn.closest('tr');
    return {
      name:       c.querySelector('.card-name')?.textContent.trim() || c.dataset.name || 'N/A',
      phone:      c.dataset.phone    || 'N/A',
      email:      c.dataset.email    || 'N/A',
      idtype:     c.dataset.idtype   || 'N/A',
      idnumber:   c.dataset.idnumber || 'N/A',
      idexpiry:   c.dataset.idexpiry || 'N/A',
      address:    c.dataset.address  || 'N/A',
      contact:    c.dataset.contact  || 'N/A',
      properties: c.dataset.properties || 0,
    };
  }

  function extractTenantData(btn) {
    const row = btn.closest('tr') || btn.closest('.card');
    return {
      id:            row.dataset.tenantId      || 'N/A',
      name:          row.dataset.name          || 'N/A',
      phone:         row.dataset.phone         || 'N/A',
      email:         row.dataset.email         || 'N/A',
      address:       row.dataset.address       || 'N/A',
      idtype:        row.dataset.idtype        || 'N/A',
      idnumber:      row.dataset.idnumber      || 'N/A',
      idexpiry:      row.dataset.idexpiry      || 'N/A',
      contact:       row.dataset.contact       || 'N/A',
      contractStart: row.dataset.contractStart || 'N/A',
      contractEnd:   row.dataset.contractEnd   || 'N/A',
      frequency:     row.dataset.frequency     || 'N/A',
      landlord:      row.dataset.landlord      || 'N/A',
      rent:          row.dataset.rent          || 'N/A',
      status:        row.dataset.status        || 'N/A',
    };
  }


  // ─────────────────────────────────────────────
  // FIELD RENDERER  (single source of truth)
  // ─────────────────────────────────────────────

  function formatViewValue(field, rawValue) {
    const val = rawValue ?? field.fallback ?? 'N/A';

    if (field.format === 'currency') {
      const n = parseInt(val);
      return isNaN(n) ? (field.fallback || 'N/A') : `£${n.toLocaleString()}`;
    }
    return val || field.fallback || 'N/A';
  }

  function renderFieldView(field, data) {
    const el = document.getElementById(field.id);
    if (!el) return;

    const raw = data[field.key];

    if (field.type === 'badge') {
      const val = raw || '';
      el.textContent = val.charAt(0).toUpperCase() + val.slice(1);
      el.className = `status-badge-modal status-${val}`;
      return;
    }

    if (field.type === 'computed') return; // handled separately

    el.textContent = formatViewValue(field, raw);
  }

  function renderFieldEdit(field, data) {
    if (!field.editable) return; // skip read-only fields in edit mode
    const el = document.getElementById(field.id);
    if (!el) return;

    const raw = data[field.key] ?? '';

    switch (field.type) {
      case 'select': {
        const opts = (field.options || [])
          .map(o => `<option value="${o}" ${o == raw ? 'selected' : ''}>${o}</option>`)
          .join('');
        el.innerHTML = `<select data-field-id="${field.id}">${opts}</select>`;
        break;
      }
      case 'date':
        el.innerHTML = `<input class="date-input" type="date" value="${raw}" data-field-id="${field.id}">`;
        break;
      case 'number':
        el.innerHTML = `<input type="number" value="${raw}" data-field-id="${field.id}">`;
        break;
      default: // text
        el.innerHTML = `<input type="text" value="${raw}" data-field-id="${field.id}">`;
    }

    // Keep formState in sync on input change
    const input = el.querySelector('input, select');
    if (input) {
      input.addEventListener('change', () => {
        state.formState[field.id] = input.value;
      });
      // seed initial value
      state.formState[field.id] = input.value;
    }
  }

  // Render ALL fields for an entity (view or edit)
  function renderFields(entityType, data, mode) {
    const schema = SCHEMAS[entityType];
    if (!schema) return;

    schema.forEach(field => {
      if (mode === 'edit') {
        renderFieldEdit(field, data);   // editable → input; non-editable → view
        if (!field.editable) renderFieldView(field, data);
      } else {
        renderFieldView(field, data);
      }
    });
  }


  // ─────────────────────────────────────────────
  // COMPUTED FIELDS  (e.g. payment due date)
  // ─────────────────────────────────────────────

  function computePaymentDue(data) {
    const el = document.getElementById('p-contract-duedate');
    if (!el) return;

    if (!data.startDate || !data.frequency) {
      el.textContent = '';
      return;
    }

    const freq  = (data.frequency || '').toLowerCase();
    const today = new Date();
    let due     = new Date(data.startDate);

    const advance = () => {
      if      (freq === 'monthly')   due.setMonth(due.getMonth() + 1);
      else if (freq === 'weekly')    due.setDate(due.getDate() + 7);
      else if (freq === 'biweekly')  due.setDate(due.getDate() + 14);
      else if (freq === 'quarterly') due.setMonth(due.getMonth() + 3);
    };

    while (due < today) advance();

    el.textContent = due.toISOString().split('T')[0];
  }


  // ─────────────────────────────────────────────
  // TITLE FIELDS  (modal headings per entity)
  // ─────────────────────────────────────────────

  const TITLE_MAP = {
    property: { titleId: 'propertyTitle', titleKey: 'address' },
    landlord: { titleId: 'landlordName',  titleKey: 'name'    },
    tenant:   { titleId: 'tenantName',    titleKey: 'name'    },
  };

  function renderTitle(entityType, data) {
    const map = TITLE_MAP[entityType];
    if (!map) return;
    const el = document.getElementById(map.titleId);
    if (el) el.textContent = data[map.titleKey] || '';
  }


  // ─────────────────────────────────────────────
  // MODAL OPEN / CLOSE
  // ─────────────────────────────────────────────

  const MODAL_IDS = {
    property: 'viewPropertyModal',
    landlord: 'viewLandlordModal',
    tenant:   'viewTenantModal',
  };

  const TAB_PREFIXES = {
    property: 'p-tab-',
    landlord: 'l-tab-',
    tenant:   't-tab-',
  };

  const DEFAULT_TABS = {
    property: 'property',
    landlord: 'landlord',
    tenant:   'tenant',
  };

  function openModal(entityType) {
    const id = MODAL_IDS[entityType];
    state.modal = document.getElementById(id);
    if (!state.modal) return;

    state.modal.classList.add('active');
    document.body.style.overflow = 'hidden';

    state.modal.onclick = (e) => {
      if (e.target === state.modal) closeModal();
    };
  }

  function closeModal() {
    if (!state.modal) return;

    state.modal.classList.remove('active');
    document.body.style.overflow = 'auto';

    // Remove footer if present
    state.modal.querySelector('.action-modal-footer')?.remove();

    // Reset state
    state.modal      = null;
    state.entity     = null;
    state.entityType = null;
    state.mode       = 'view';
    state.formState  = {};
  }


  // ─────────────────────────────────────────────
  // SAVE BUTTON  (shown only in edit mode)
  // ─────────────────────────────────────────────

  function showSaveButton() {
    // Remove stale one first
    state.modal?.querySelector('.action-modal-footer')?.remove();

    const footer = document.createElement('div');
    footer.className = 'action-modal-footer';
    footer.innerHTML = `
      <button class="modal-btn-save-changes modal-btn-save" data-action="save">
        <i class="fa-solid fa-check"></i> Save Changes
      </button>
    `;
    state.modal?.querySelector('.action-modal-container')?.appendChild(footer);
  }

  function hideSaveButton() {
    state.modal?.querySelector('.action-modal-footer')?.remove();
  }


  // ─────────────────────────────────────────────
  // ASYNC SIDE-EFFECTS  (notes, files)
  // ─────────────────────────────────────────────

  function fetchNotes(propertyId) {
    fetch(`notes.php?property_id=${propertyId}`)
      .then(r => r.json())
      .then(notes => {
        const list = document.getElementById('p-notesList');
        if (!list) return;
        list.innerHTML = '';
        notes.forEach(n => {
          list.insertAdjacentHTML('afterbegin', `
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
  }

  function fetchFiles(propertyId) {
    fetch(`get_files.php?property_id=${propertyId}`)
      .then(r => r.json())
      .then(files => {
        const list = document.querySelector('#p-tab-files .files-list');
        if (!list) return;
        list.innerHTML = '';
        files.forEach(f => {
          list.insertAdjacentHTML('beforeend', `
            <div class="file-item">
              <i class="fa-solid fa-file-pdf file-icon"></i>
              <span class="file-name">${f.file_name}</span>
              <button class="file-open-btn" onclick="window.open('${f.file_path}', '_blank')">Open</button>
            </div>
          `);
        });
      });
  }


  // ─────────────────────────────────────────────
  // CORE OPEN FLOW  (shared by view + edit)
  // ─────────────────────────────────────────────

  function openEntityModal(entityType, data, mode) {
    state.entity     = data;
    state.entityType = entityType;
    state.mode       = mode;
    state.formState  = {};

    openModal(entityType);
    renderTitle(entityType, data);
    renderFields(entityType, data, mode);

    if (entityType === 'property') {
      computePaymentDue(data);
      fetchNotes(data._id);
      fetchFiles(data._id);
    }

    if (mode === 'edit') showSaveButton();
    else hideSaveButton();

    switchTab(DEFAULT_TABS[entityType]);
  }


  // ─────────────────────────────────────────────
  // TAB SWITCHING
  // ─────────────────────────────────────────────

  function switchTab(tabId, event) {
    if (!state.modal) return;

    state.modal.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    state.modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

    const prefix = TAB_PREFIXES[state.entityType] || 'l-tab-';
    state.modal.querySelector(`#${prefix}${tabId}`)?.classList.add('active');

    if (event) event.currentTarget.classList.add('active');
  }


  // ─────────────────────────────────────────────
  // SAVE  (reads from formState, not DOM queries)
  // ─────────────────────────────────────────────

  function saveProperty() {
    const map     = SAVE_MAP.property;
    const payload = { id: state.entity._id };

    Object.entries(map).forEach(([payloadKey, fieldId]) => {
      if (fieldId === '_id') return;
      payload[payloadKey] = state.formState[fieldId]
        ?? document.getElementById(fieldId)?.querySelector('input, select')?.value
        ?? '';
    });

    fetch('update_property.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(() => location.reload());
  }


  // ─────────────────────────────────────────────
  // ADD NOTE
  // ─────────────────────────────────────────────

  function addNote() {
    if (!state.modal) return;

    const textarea = document.getElementById('p-noteTextarea');
    const text     = textarea?.value.trim();
    if (!text) return;

    fetch('notes.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    `property_id=${state.entity._id}&note_text=${encodeURIComponent(text)}`,
    })
      .then(r => r.json())
      .then(data => {
        const list = document.getElementById('p-notesList');
        list?.insertAdjacentHTML('afterbegin', `
          <div class="note-item">
            <div class="note-text">${escapeHtml(text)}</div>
            <div class="note-timestamp">
              <i class="fa-solid fa-clock"></i>
              ${data.created_at} - ${data.created_by ?? 'Unknown'}
            </div>
          </div>
        `);
        if (textarea) textarea.value = '';
      });
  }


  // ─────────────────────────────────────────────
  // UTILITY
  // ─────────────────────────────────────────────

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }


  // ─────────────────────────────────────────────
  // EVENT DELEGATION
  // ─────────────────────────────────────────────

  document.addEventListener('click', (e) => {

    // Tab buttons
    const tabBtn = e.target.closest('.tab-btn');
    if (tabBtn) {
      switchTab(tabBtn.dataset.tab, e);
      return;
    }

    // Save button (delegated — avoids re-injecting handlers)
    const saveBtn = e.target.closest('[data-action="save"]');
    if (saveBtn) {
      if (state.entityType === 'property') saveProperty();
      return;
    }
  });


  // ─────────────────────────────────────────────
  // PUBLIC API
  // ─────────────────────────────────────────────

  return {

    // ── Entry points ────────────────────────────

    viewProperty(btn) {
      openEntityModal('property', extractPropertyData(btn), 'view');
    },

    editProperty(btn) {
      openEntityModal('property', extractPropertyData(btn), 'edit');
    },

    viewLandlord(btn) {
      openEntityModal('landlord', extractLandlordData(btn), 'view');
    },

    viewTenant(btn) {
      openEntityModal('tenant', extractTenantData(btn), 'view');
    },

    // ── Exposed utilities ───────────────────────

    closeModal,
    switchTab,
    addNote,
    saveProperty,
  };

})();


// ─────────────────────────────────────────────
// Legacy helper (referenced in HTML onclick)
// ─────────────────────────────────────────────
function openFile(fileType) {
  alert(`Opening ${fileType.toUpperCase()} file... (Implement PDF viewer)`);
}