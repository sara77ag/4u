window.actionbtn = (() => {

  const state = {
    modal:      null,
    entity:     null,
    entityType: null,
    mode:       'view',
    formState:  {},  
  };

  const SCHEMAS = {

    property: [
      { id: 'p-property-landlord',   key: 'landlord',        type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record'   },
      { id: 'p-property-status',     key: 'status',          type: 'badge',    tab: 'property',  editable: false,  fallback: 'No Record'  },
      { id: 'p-property-price',      key: 'price',           type: 'number',   tab: 'property',  editable: true,  prefix: '£', format: 'currency',  fallback: 'No Record'  },
      { id: 'p-property-type',       key: 'type',            type: 'select',   tab: 'property',  editable: true,  options: ['Flat','House','Apartment','Bungalow'],  fallback: 'No Record'  },
      { id: 'p-property-tenant',     key: 'tenant',          type: 'text',     tab: 'property',  editable: false, fallback: 'No Tenant' },
      { id: 'p-property-rooms',      key: 'rooms',           type: 'number',   tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'p-property-bathrooms',  key: 'bathrooms',       type: 'number',   tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'p-property-epc',        key: 'epc',             type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'p-property-pets',       key: 'pets',            type: 'select',   tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'p-property-garden',     key: 'garden',          type: 'select',   tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'p-property-council',    key: 'council',         type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record' },

      { id: 'p-contract-landlord',   key: 'landlord',        type: 'text',     tab: 'contract',  editable: false,  fallback: 'No Record' },
      { id: 'p-contract-tenant',     key: 'tenant',          type: 'text',     tab: 'contract',  editable: true, fallback: 'No Tenant' },
      { id: 'p-contract-rent',       key: 'rent',            type: 'number',   tab: 'contract',  editable: true,  prefix: '£', format: 'currency', fallback: 'No Rent' },
      { id: 'p-contract-frequency',  key: 'frequency',       type: 'select',   tab: 'contract',  editable: true,  options: ['Monthly','Weekly','Biweekly','Quarterly'], fallback: 'Not Set' },
      { id: 'p-contract-start',      key: 'startDate',       type: 'date',     tab: 'contract',  editable: true,  fallback: 'No Contract' },
      { id: 'p-contract-end',        key: 'endDate',         type: 'date',     tab: 'contract',  editable: true,  fallback: 'No Contract' },
      { id: 'p-contract-duedate',    key: '_dueDate',        type: 'computed', tab: 'contract',  editable: false, fallback: 'Not Set' },

      { id: 'p-date-landlord-id',    key: 'landlordIdExpiry', type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'p-date-tenant-id',      key: 'tenantIdExpiry',   type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'p-date-contract-end',   key: 'endDate',          type: 'date',    tab: 'dates',     editable: false, fallback: 'No Record' },
      { id: 'p-date-gas',            key: 'gasDate',          type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'p-date-electric',       key: 'electricDate',     type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'p-date-epc',            key: 'epcDate',          type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
    ],

    landlord: [
      { id: 'l-landlord-name',           key: 'name',           type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-phone',          key: 'phone',          type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-email',          key: 'email',          type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-idtype',         key: 'id_type',        type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-idnumber',       key: 'id_number',      type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-idexpiry',       key: 'id_expiry_date', type: 'date',   tab: 'landlord', editable: true },
      { id: 'l-landlord-address',        key: 'address',        type: 'text',   tab: 'landlord', editable: true },
      { id: 'l-landlord-contact-method', key: 'contact_method', type: 'select', tab: 'landlord', editable: true, options: ['email', 'phone', 'sms'] }
    ],

    tenant: [
      { id: 't-tenant-name',                  key: 'name',          type: 'text',   tab: 'tenant',  editable: true  },
      { id: 't-tenant-phone',                 key: 'phone',         type: 'text',   tab: 'tenant',  editable: true  },
      { id: 't-tenant-email',                 key: 'email',         type: 'text',   tab: 'tenant',  editable: true  },
      { id: 't-tenant-idtype',                key: 'idtype',        type: 'text',   tab: 'tenant',  editable: true  },
      { id: 't-tenant-idnumber',              key: 'idnumber',      type: 'text',   tab: 'tenant',  editable: true  },
      { id: 'l-tenant-idexpiry',              key: 'idexpiry',      type: 'date',   tab: 'tenant',  editable: true  },
      { id: 'l-tenant-address',               key: 'address',       type: 'text',   tab: 'tenant',  editable: false  },
      { id: 't-tenant-contact',               key: 'contact',       type: 'select', editable: true, options: ['email','phone','sms'] },
      { id: 't-currentTenancy-address',       key: 'address',       type: 'text',   tab: 'currentTenancy', editable: false },
      { id: 't-currentTenancy-landlord',      key: 'landlord',      type: 'text',   tab: 'currentTenancy', editable: false },
      { id: 't-currentTenancy-contractStart', key: 'contractStart', type: 'date',   tab: 'currentTenancy', editable: false },
      { id: 't-currentTenancy-contractEnd',   key: 'contractEnd',   type: 'date',   tab: 'currentTenancy', editable: false },
      { id: 't-currentTenancy-rent',          key: 'rent',          type: 'number', tab: 'currentTenancy', editable: false, prefix: '£', format: 'currency' },
      { id: 't-currentTenancy-status',        key: 'status',        type: 'text',   tab: 'currentTenancy', editable: false },
    ],

    viewing: [
      { id: 'v-viewing-id',           key: '_id',           type: 'text',   tab: 'viewing', editable: false },
      { id: 'v-viewing-propertyid',   key: 'property_id',   type: 'text',   tab: 'viewing', editable: false },
      { id: 'v-viewing-viewer',       key: 'viewer_name',   type: 'text',   tab: 'viewing', editable: true },
      { id: 'v-viewing-phone',        key: 'viewer_phone',  type: 'text',   tab: 'viewing', editable: true },
      { id: 'v-viewing-status',       key: 'status',        type: 'select', tab: 'viewing', editable: true, options: ['scheduled', 'completed', 'cancelled', 'no_show'] },
      { id: 'v-viewing-datetime',     key: 'date_time',     type: 'datetime', tab: 'viewing', editable: true },
      { id: 'v-viewing-userid',       key: 'user_id',       type: 'text',   tab: 'viewing', editable: false },
      { id: 'v-viewing-email',        key: 'email',         type: 'text',   tab: 'viewing', editable: true },
      { id: 'v-viewing-archived',     key: 'archived',      type: 'select', tab: 'viewing', editable: true, options: ['0', '1'] },
      { id: 'v-viewing-datearchived', key: 'date_archived', type: 'date',   tab: 'viewing', editable: false },

      { id: 'v-property-landlord',    key: 'landlord',      type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-type',        key: 'type',          type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-status',      key: 'property_status', type: 'badge', tab: 'property', editable: false },
      { id: 'v-property-price',       key: 'price',         type: 'number', tab: 'property', editable: false },
      { id: 'v-property-tenant',      key: 'tenant',        type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-rooms',       key: 'rooms',         type: 'number', tab: 'property', editable: false },
      { id: 'v-property-bathrooms',   key: 'bathrooms',     type: 'number', tab: 'property', editable: false },
      { id: 'v-property-epc',         key: 'epc',           type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-pets',        key: 'pets',          type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-garden',      key: 'garden',        type: 'text',   tab: 'property', editable: false },
      { id: 'v-property-council',     key: 'council',       type: 'text',   tab: 'property', editable: false },
    ],

    user: [
      { id: 'u-user-username',          key: 'username',            type: 'text',   tab: 'user',  editable: false },
      { id: 'u-user-name',              key: 'name',                type: 'text',   tab: 'user',  editable: true },
      { id: 'u-user-phone',             key: 'phone',               type: 'text',   tab: 'user',  editable: true },
      { id: 'u-user-email',             key: 'email',               type: 'text',   tab: 'user',  editable: true },
      { id: 'u-user-password',          key: 'password',            type: 'text',   tab: 'user',  editable: true },
      { id: 'u-user-passwordresetdate', key: 'password_reset_date', type: 'date',   tab: 'user',  editable: false },
      { id: 'u-user-role',              key: 'role',                type: 'select', tab: 'user',  editable: true, options: ['admin', 'agent', 'read_only'] },
      { id: 'u-user-archived',          key: 'archived',            type: 'select', tab: 'user',  editable: true, options: ['0', '1'] },
      { id: 'u-user-datearchived',      key: 'date_archived',       type: 'date',   tab: 'user',  editable: false },

      { id: 'u-user-IdNumber',          key: 'id_number',           type: 'text',   tab: 'files', editable: true },
      { id: 'u-user-Idexpirydate',      key: 'id_expiry_date',      type: 'date',   tab: 'files', editable: true },
    ],

    importantdate: [
      { id: 'i-date-landlord-id',    key: 'landlordIdExpiry', type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'i-date-tenant-id',      key: 'tenantIdExpiry',   type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'i-date-contract-end',   key: 'endDate',          type: 'date',    tab: 'dates',     editable: false, fallback: 'No Record' },
      { id: 'i-date-gas',            key: 'gasDate',          type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'i-date-electric',       key: 'electricDate',     type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },
      { id: 'i-date-epc',            key: 'epcDate',          type: 'date',    tab: 'dates',     editable: true,  fallback: 'No Record' },

      { id: 'i-property-landlord',   key: 'landlord',        type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record'   },
      { id: 'i-property-status',     key: 'status',          type: 'badge',    tab: 'property',  editable: false,  fallback: 'No Record'  },
      { id: 'i-property-price',      key: 'price',           type: 'number',   tab: 'property',  editable: true,  prefix: '£', format: 'currency',  fallback: 'No Record'  },
      { id: 'i-property-type',       key: 'type',            type: 'select',   tab: 'property',  editable: true,  options: ['Flat','House','Apartment','Bungalow'],  fallback: 'No Record'  },
      { id: 'i-property-tenant',     key: 'tenant',          type: 'text',     tab: 'property',  editable: false, fallback: 'No Tenant' },
      { id: 'i-property-rooms',      key: 'rooms',           type: 'number',   tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'i-property-bathrooms',  key: 'bathrooms',       type: 'number',   tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'i-property-epc',        key: 'epc',             type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record' },
      { id: 'i-property-pets',       key: 'pets',            type: 'select',   tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'i-property-garden',     key: 'garden',          type: 'select',   tab: 'property',  editable: true,  options: ['Yes','No'] },
      { id: 'i-property-council',    key: 'council',         type: 'text',     tab: 'property',  editable: true,  fallback: 'No Record' },
    ]
  };

  const SAVE_MAP = {
    property: {
      id:         '_id',
      landlord:   'p-property-landlord',
      type:       'p-property-type',
      price:      'p-property-price',
      tenant:     'p-property-tenant',
      rooms:      'p-property-rooms',
      bathrooms:  'p-property-bathrooms',
      epc:        'p-property-epc',
      pets:       'p-property-pets',
      garden:     'p-property-garden',
      council:    'p-property-council',
      rent:       'p-contract-rent',
      frequency:  'p-contract-frequency',
      start:      'p-contract-start',
      end:        'p-contract-end',
      landlordId: 'p-date-landlord-id',
      tenantId:   'p-date-tenant-id',
      gas:        'p-date-gas',
      electric:   'p-date-electric',
      epcDate:    'p-date-epc',
    },

    landlord: {
      id:             'landlord_id',
      name:           'l-landlord-name',
      phone:          'l-landlord-phone',
      email:          'l-landlord-email',
      address:        'l-landlord-address',
      id_type:        'l-landlord-idtype',
      id_number:      'l-landlord-idnumber',
      id_expiry_date: 'l-landlord-idexpiry',
      contact_method: 'l-landlord-contact-method'
    },

    viewing: {
      _id:              'v-viewing-id',
      property_id:     'v-viewing-propertyid',
      viewer_name:     'v-viewing-viewer',
      viewer_phone:    'v-viewing-phone',
      status:          'v-viewing-status',
      date_time:       'v-viewing-datetime',
      user_id:         'v-viewing-userid',
      email:           'v-viewing-email',
      archived:        'v-viewing-archived',
      date_archived:    'v-viewing-datearchived',
    },

    user: {
      username:         'u-user-username',
      name:             'u-user-name',
      phone:            'u-user-phone',
      email:            'u-user-email',
      password:         'u-user-password',
      password_reset_date:'u-user-passwordresetdate',
      id_number:         'u-user-IdNumber',
      id_expiry_date:   'u-user-Idexpirydate',
      role:             'u-user-role',
      archived:         'u-user-archived',
      date_archived:     'u-user-datearchived',
    },

  };

  // Extract Data

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
    const c = btn.closest('.card');
    return {
      _id:            c.dataset.id || c.dataset.landlordId,
      name:           c.querySelector('.card-name')?.textContent.trim() || '',
      phone:          c.dataset.phone          || '',
      email:          c.dataset.email          || '',
      address:        c.dataset.address        || '',
      id_type:        c.dataset.idtype         || '',
      id_number:      c.dataset.idnumber       || '',
      id_expiry_date: c.dataset.idexpiry       || '',
      contact_method: c.dataset.contactMethod  || '',
      properties:     c.dataset.properties     || 0,
    };
  }
  
  function extractTenantData(btn) {
    const row = btn.closest('tr') || btn.closest('.card');
    return {
      _id:           row.dataset.tenantId      || '',
      id:            row.dataset.tenantId      || '',
      name:          row.dataset.name          || '',
      phone:         row.dataset.phone         || '',
      email:         row.dataset.email         || '',
      address:       row.dataset.address       || '',
      idtype:        row.dataset.idtype        || '',
      idnumber:      row.dataset.idnumber      || '',
      idexpiry:      row.dataset.idexpiry      || '',
      contact:       row.dataset.contact       || '',
      contractStart: row.dataset.contractStart || '',
      contractEnd:   row.dataset.contractEnd   || '',
      frequency:     row.dataset.frequency     || '',
      landlord:      row.dataset.landlord      || '',
      rent:          row.dataset.rent          || '',
      status:        row.dataset.status        || '',
    };
  }

  function extractViewingData(btn) {
    const row = btn.closest('tr') || btn.closest('.card');
    return {
      _id:             row.dataset.bookingid || '',
      property_id:     row.dataset.propertyid || '',
      viewer_name:     row.dataset.viewer || '',
      viewer_phone:    row.dataset.phone || '',
      status:          row.dataset.status || '',
      date_time:       row.dataset.datetime || '',
      user_id:         row.dataset.userid || '',
      email:           row.dataset.email || '',
      archived:        row.dataset.archived || '',
      date_archived:   row.dataset.datearchived || '',

      landlord:        row.dataset.landlord || '',
      type:            row.dataset.type || '',
      property_status: row.dataset.propertystatus || '',
      price:           row.dataset.price || '',
      tenant:          row.dataset.tenant || '',
      rooms:           row.dataset.rooms || '',
      bathrooms:       row.dataset.bathrooms || '',
      epc:             row.dataset.epc || '',
      pets:            row.dataset.pets === '1' ? 'Yes' : row.dataset.pets === '0' ? 'No' : '',
      garden:          row.dataset.garden === '1' ? 'Yes' : row.dataset.garden === '0' ? 'No' : '',
      council:         row.dataset.council || ''
    };
  }

  function extractUserData(btn) {
    const row = btn.closest('tr') || btn.closest('.card');
    return {
      _id:                 row.dataset.userid || row.dataset.id || '',
      username:            row.dataset.username || '',
      name:                row.dataset.name || '',
      phone:               row.dataset.phone || '',
      email:               row.dataset.email || '',
      password:            row.dataset.password || '',
      password_reset_date: row.dataset.passwordresetdate || '',
      id_number:           row.dataset.idnumber || '',
      id_expiry_date:      row.dataset.idexpirydate || '',
      role:                row.dataset.role || '',
      archived:            row.dataset.archived || '',
      date_archived:       row.dataset.datearchived || ''
    };
  }

  // Render

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
    if (field.type === 'computed') return;

    const isEmpty = raw === null || raw === undefined || raw === '';
    const value   = formatViewValue(field, raw);
    el.innerHTML  = `<span class="${isEmpty ? 'tenant-vacant' : ''}">${value}</span>`;
  }

  function renderFieldEdit(field, data) {
    if (!field.editable) return;
    const el = document.getElementById(field.id);
    if (!el) return;

    const raw         = data[field.key] ?? '';
    const isEmpty     = raw === null || raw === undefined || raw === '';
    const placeholder = field.fallback || '';

    switch (field.type) {
      case 'select': {
        const opts = (field.options || [])
          .map(o => `<option value="${o}" ${o == raw ? 'selected' : ''}>${o}</option>`)
          .join('');
        const emptyOption = isEmpty ? `<option value="" disabled selected>${placeholder}</option>` : '';
        el.innerHTML = `<select data-field-id="${field.id}">${emptyOption}${opts}</select>`;
        break;
      }
      case 'datetime': {
        const dtValue = raw ? raw.replace(' ', 'T').slice(0, 16) : '';
        el.innerHTML = `<input type="datetime-local" value="${dtValue}" data-field-id="${field.id}">`;
        break;
      }
      case 'date':
        el.innerHTML = `<input class="date-input" type="date" value="${raw}" data-field-id="${field.id}">`;
        break;
      case 'number':
        el.innerHTML = `<input type="number" value="${raw}" placeholder="${isEmpty ? placeholder : ''}" data-field-id="${field.id}">`;
        break;
      default:
        el.innerHTML = `<input type="text" value="${raw}" placeholder="${isEmpty ? placeholder : ''}" data-field-id="${field.id}">`;
    }
  }

  function renderFields(entityType, data, mode) {
    const schema = SCHEMAS[entityType];
    if (!schema) return;
    schema.forEach(field => {
      if (mode === 'edit') {
        renderFieldEdit(field, data);
        if (!field.editable) renderFieldView(field, data);
      } else {
        renderFieldView(field, data);
      }
    });
  }

  // Landlord Properties Tab
function fetchLandlordProperties(landlordId) {
  const container = document.querySelector('.contant-row-p');
  const count     = document.getElementById('l-properties-count');
  if (!container) return;

  const header = container.querySelector('.data-row.header');
  container.innerHTML = '';
  if (header) container.appendChild(header);

  // Loading indicator
  const loading = document.createElement('div');
  loading.className = 'data-row';
  loading.innerHTML = `<div class="cell" style="grid-column:1/-1; color:var(--text-muted);">
    <i class="fa-solid fa-spinner fa-spin"></i> Loading...
  </div>`;
  container.appendChild(loading);

  fetch(`get_landlord_properties.php?landlord_id=${landlordId}`)
    .then(r => r.json())
    .then(properties => {
      const header = container.querySelector('.data-row.header');
      container.innerHTML = '';
      if (header) container.appendChild(header);

      if (count) count.textContent = properties.length;

      if (!properties.length) {
        const empty = document.createElement('div');
        empty.className = 'data-row';
        empty.innerHTML = `<div class="cell" style="grid-column:1/-1; text-align:center; color:var(--text-muted);">No properties found</div>`;
        container.appendChild(empty);
        return;
      }

      properties.forEach(p => {
        const row = document.createElement('div');
        row.className = 'data-row';

        const status      = p.status === 'occupied' ? 'occupied' : 'available';
        const statusLabel = status === 'occupied' ? 'Occupied' : 'Vacant';
        const statusClass = status === 'occupied' ? 'status-occupied' : 'status-available';
        const tenant      = p.tenant_name
          ? escapeHtml(p.tenant_name)
          : `<span class="tenant-vacant">No Tenant</span>`;
        const rent        = p.status === 'occupied' && p.contract_rent
          ? `£${Number(p.contract_rent).toLocaleString()}`
          : `£${Number(p.monthly_rent).toLocaleString()}`;

        row.innerHTML = `
          <div class="cell">${escapeHtml(p.property_address)}</div>
          <div class="cell">${escapeHtml(p.property_type)}</div>
          <div class="cell"><span class="status-badge ${statusClass}">${statusLabel}</span></div>
          <div class="cell">${tenant}</div>
          <div class="cell"><span class="rent">${rent}</span></div>`;

        container.appendChild(row);
      });
    })
    .catch(err => {
      console.error('fetchLandlordProperties error:', err);
      const header = container.querySelector('.data-row.header');
      container.innerHTML = '';
      if (header) container.appendChild(header);
      const errRow = document.createElement('div');
      errRow.className = 'data-row';
      errRow.innerHTML = `<div class="cell" style="color:red;">Failed to load properties</div>`;
      container.appendChild(errRow);
    });
}

  // Compute Payment Due

  function computePaymentDue(data) {
    const el = document.getElementById('p-contract-duedate');
    if (!el) return;

    if (!data.startDate || !data.frequency) {
      el.innerHTML = `<span class="tenant-vacant">Not Set</span>`;
      return;
    }

    const freq      = (data.frequency || '').toLowerCase().trim();
    const validFreqs = ['monthly', 'weekly', 'biweekly', 'quarterly'];
    if (!validFreqs.includes(freq)) {
      el.innerHTML = `<span class="tenant-vacant">Not Set</span>`;
      return;
    }

    const today = new Date();
    let due     = new Date(data.startDate);
    if (isNaN(due.getTime())) {
      el.innerHTML = `<span class="tenant-vacant">Not Set</span>`;
      return;
    }

    const advance = () => {
      if      (freq === 'monthly')   due.setMonth(due.getMonth() + 1);
      else if (freq === 'weekly')    due.setDate(due.getDate() + 7);
      else if (freq === 'biweekly')  due.setDate(due.getDate() + 14);
      else if (freq === 'quarterly') due.setMonth(due.getMonth() + 3);
    };

    let max = 1000;
    while (due < today && max-- > 0) advance();

    if (max <= 0) {
      el.innerHTML = `<span class="tenant-vacant">Not Set</span>`;
      return;
    }

    el.textContent = due.toISOString().split('T')[0];
  }

  // Modal

  const TITLE_MAP = {
    property: { titleId: 'propertyTitle', titleKey: 'address' },
    landlord: { titleId: 'landlordName',  titleKey: 'name'    },
    tenant:   { titleId: 'tenantName',    titleKey: 'name'    },
    user:     { titleId: 'username',      titleKey: 'name' },
    viewing:  { titleId: 'viewerName', titleKey: 'viewer_name' },
    importantdate: { titleId: 'propertyTitle', titleKey: 'address' },
  };

  function renderTitle(entityType, data) {
    const map = TITLE_MAP[entityType];
    if (!map) return;
    const el = document.getElementById(map.titleId);
    if (el) el.textContent = data[map.titleKey] || '';
  }

  const MODAL_IDS = {
    property: 'viewPropertyModal',
    landlord: 'viewLandlordModal',
    tenant:   'viewTenantModal',
    user: 'viewUserModal',
    viewing: 'viewViewingModal',
    importantdate: 'viewImportantDatesModal',
  };

  const TAB_PREFIXES = {
    property: 'p-tab-',
    landlord: 'l-tab-',
    tenant:   't-tab-',
    user: 'u-tab-',
    viewing: 'v-tab-',
    importantdate: 'i-tab-',
  };

  const DEFAULT_TABS = {
    property: 'property',
    landlord: 'landlord',
    tenant:   'tenant',
    user: 'user',
    viewing: 'viewing',
  };

  function openModal(entityType) {
    const id = MODAL_IDS[entityType];
    state.modal = document.getElementById(id);
    if (!state.modal) return;
    state.modal.classList.add('active');
    document.body.style.overflow = 'hidden';
    state.modal.addEventListener('click', (e) => {
      if (e.target === state.modal) closeModal();
    }, { once: true });
  }

  function closeModal() {
    if (!state.modal) return;
    state.modal.classList.remove('active');
    document.body.style.overflow = 'auto';
    state.modal.querySelector('.action-modal-footer')?.remove();
    state.modal      = null;
    state.entity     = null;
    state.entityType = null;
    state.mode       = 'view';
    state.formState  = {};
  }

  function showSaveButton() {
    state.modal?.querySelector('.action-modal-footer')?.remove();
    const footer = document.createElement('div');
    footer.className = 'action-modal-footer';
    footer.innerHTML = `
      <button class="modal-btn-save-changes modal-btn-save" data-action="save">
        <i class="fa-solid fa-check"></i> Save Changes
      </button>`;
    state.modal?.querySelector('.action-modal-container')?.appendChild(footer);
  }

  function hideSaveButton() {
    state.modal?.querySelector('.action-modal-footer')?.remove();
  }

  // Notes

  function fetchNotes(id, type) {
    if (!id || id === 'undefined' || id === '0') {
      const list = state.modal?.querySelector('[data-notes-list]');
      if (list) list.innerHTML = '';
      return;
    }

    fetch(`notes.php?entity_id=${id}&entity_type=${type}`)
      .then(r => r.json())
      .then(notes => {
        const list = state.modal?.querySelector('[data-notes-list]');
        if (!list) return;
        list.innerHTML = '';
        notes.forEach(n => {
          list.insertAdjacentHTML('beforeend', `
            <div class="note-item">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="note-timestamp">
                  <i class="fa-solid fa-clock"></i>
                  ${n.created_at} - ${n.created_by ?? 'Unknown'}
                </div>
                ${state.mode === 'edit' ? `
                  <button class="delete-btn" data-id="${n.note_id}">
                    <i class="fa-solid fa-trash"></i>
                  </button>` : ''}
              </div>
              <div class="note-text">${escapeHtml(n.note_text)}</div>
            </div>`);
        });
      })
      .catch(err => console.error('fetchNotes error:', err));
  }

  // Open Modal

  function openEntityModal(entityType, data, mode) {
    state.entity     = data;
    state.entityType = entityType;
    state.mode       = mode;
    state.formState  = {};

    openModal(entityType);
    if (!state.modal) return;

    renderTitle(entityType, data);
    renderFields(entityType, data, mode);

    if (entityType === 'property') { computePaymentDue(data);}
    if (entityType === 'landlord') fetchLandlordProperties(data._id);
    if (entityType === 'tenant')   fetchTenantHistory(data._id || data.id);

    fetchNotes(data._id || data.id, entityType);

    const notesInput = state.modal.querySelector('.notes-input-section');
    if (notesInput) {
      notesInput.style.display = (mode === 'edit') ? 'flex' : 'none';
    }

    if (mode === 'edit') showSaveButton();
    else hideSaveButton();

    switchTab(DEFAULT_TABS[entityType]);
  }

  function switchTab(tabId, event) {
    if (!state.modal) return;
    if (!tabId) return;
    state.modal.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    state.modal.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const prefix = TAB_PREFIXES[state.entityType] || 't-tab-';
    const tabEl = state.modal.querySelector(`#${prefix}${tabId}`);
    if (tabEl) tabEl.classList.add('active');
    if (event && event.currentTarget) event.currentTarget.classList.add('active');
  }

  // Save 

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

  function saveLandlord() {
    const payload = {
      id:             state.entity._id,
      type:           'landlord',
      name:           document.querySelector('#l-landlord-name input')?.value      || '',
      phone:          document.querySelector('#l-landlord-phone input')?.value     || '',
      email:          document.querySelector('#l-landlord-email input')?.value     || '',
      address:        document.querySelector('#l-landlord-address input')?.value   || '',
      id_type:        document.querySelector('#l-landlord-idtype input')?.value    || '',
      id_number:      document.querySelector('#l-landlord-idnumber input')?.value  || '',
      id_expiry_date: document.querySelector('#l-landlord-idexpiry input')?.value  || '',
      contact_method: document.querySelector('#l-landlord-contact-method select')?.value || ''
    };

    fetch('update_property.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(payload)
    })
      .then(r => r.json())
      .then(() => location.reload())
      .catch(err => console.error(err));
  }

  function saveTenant() {
  const payload = {
    id:             state.entity._id || state.entity.id,
    name:           document.querySelector('#t-tenant-name input')?.value     || '',
    phone:          document.querySelector('#t-tenant-phone input')?.value    || '',
    email:          document.querySelector('#t-tenant-email input')?.value    || '',
    id_type:        document.querySelector('#t-tenant-idtype input')?.value   || '',
    id_number:      document.querySelector('#t-tenant-idnumber input')?.value || '',
    id_expiry_date: document.querySelector('#l-tenant-idexpiry input')?.value || '',
    contact_method: document.querySelector('#t-tenant-contact select')?.value || '',
  };

  fetch('update_tenant.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(() => location.reload())
  .catch(err => console.error(err));
}

function saveViewing() {
  const payload = {
    id:             state.entity._id || state.entity.id,
    viewer_name:           document.querySelector('#v-viewing-viewer input')?.value     || '',
    viewer_phone:          document.querySelector('#v-viewing-phone input')?.value    || '',
    status:          document.querySelector('#v-viewing-status select')?.value    || '',
    date_time:        document.querySelector('#v-viewing-datetime input')?.value   || '',
    user_id:      document.querySelector('#v-viewing-userid input')?.value || '',
    email: document.querySelector('#v-viewing-email input')?.value || '',
    archived: document.querySelector('#v-viewing-archived select')?.value || '',
    date_archived: document.querySelector('#v-viewing-datearchived input')?.value || '',
  };

  fetch('update_viewing.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    body:    JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(() => location.reload())
  .catch(err => console.error(err));
}

function saveUser() {
  const payload = {
    id:                  state.entity._id || '',
    username:            document.querySelector('#u-user-username input')?.value || state.entity.username || '',
    name:                document.querySelector('#u-user-name input')?.value || '',
    phone:               document.querySelector('#u-user-phone input')?.value || '',
    email:               document.querySelector('#u-user-email input')?.value || '',
    password:            document.querySelector('#u-user-password input')?.value || '',
    password_reset_date: document.querySelector('#u-user-passwordresetdate input')?.value || state.entity.password_reset_date || '',
    id_number:           document.querySelector('#u-user-IdNumber input')?.value || '',
    id_expiry_date:      document.querySelector('#u-user-Idexpirydate input')?.value || '',
    role:                document.querySelector('#u-user-role select')?.value || '',
    archived:            document.querySelector('#u-user-archived select')?.value || '',
    date_archived:       document.querySelector('#u-user-datearchived input')?.value || state.entity.date_archived || '',
  };

  fetch('update_user.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload)
  })
  .then(r => r.json())
  .then(() => location.reload())
  .catch(err => console.error(err));
}

  // Add Note

  function addNote() {
    if (!state.modal) return;
    const textarea = state.modal.querySelector('textarea');
    const text     = textarea?.value.trim();
    if (!text) return;

    const payload = new URLSearchParams({
      note_text:   text,
      entity_type: state.entityType,
      entity_id:   state.entity._id
    });

    fetch('notes.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    payload.toString(),
    })
      .then(async r => {
        const data = await r.json();
        if (!r.ok || data.success === false) throw new Error(data.message || 'Failed');

        const list = state.modal.querySelector('[data-notes-list]');
        if (!list) return;

        list.insertAdjacentHTML('afterbegin', `
          <div class="note-item">
            <div class="note-text">${escapeHtml(text)}</div>
            <div class="note-timestamp">
              <i class="fa-solid fa-clock"></i>
              ${data.created_at} - ${data.created_by ?? 'Unknown'}
            </div>
          </div>`);

        textarea.value = '';
      })
      .catch(err => {
        console.error('Add note error:', err);
        alert('Failed to add note');
      });
  }

  // Helpers 

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function formatDate(dateStr) {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return isNaN(d) ? dateStr : d.toLocaleDateString('en-GB');
}

  //function openViewingNotes(btn) {
  //  const data = extractViewingData(btn);
  //  openEntityModal('viewing', data, 'view');

  //  setTimeout(() => switchTab('notes'), 0);
  //}

  // Global Click Handler 

  document.addEventListener('click', (e) => {
    const tabBtn = e.target.closest('.tab-btn');
    if (tabBtn && state.modal) { switchTab(tabBtn.dataset.tab, e); return; }

    const saveBtn = e.target.closest('[data-action="save"]');
    if (saveBtn) {
      if (state.entityType === 'property') saveProperty();
      if (state.entityType === 'landlord') saveLandlord();
      if (state.entityType === 'tenant')   saveTenant();
      if (state.entityType === 'viewing') saveViewing();
      if (state.entityType === 'user') saveUser();
      return;
    }

    const deleteBtn = e.target.closest('.delete-btn');
    if (deleteBtn) {
      const noteId = deleteBtn.dataset.id;
      if (!confirm('Are you sure you want to delete this note?')) return;
      fetch('notes.php', {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    new URLSearchParams({ delete_note: 1, note_id: noteId })
      })
        .then(r => r.json())
        .then(res => {
          if (res.success) deleteBtn.closest('.note-item').remove();
          else alert('Failed to delete note');
        });
      return;
    }
  });

  // Public API

  return {
    viewProperty(btn)  { openEntityModal('property', extractPropertyData(btn), 'view'); },
    editProperty(btn)  { openEntityModal('property', extractPropertyData(btn), 'edit'); },
    viewLandlord(btn)  { openEntityModal('landlord', extractLandlordData(btn), 'view'); },
    editLandlord(btn)  { openEntityModal('landlord', extractLandlordData(btn), 'edit'); },
    viewTenant(btn)    { openEntityModal('tenant',   extractTenantData(btn),   'view'); },
    editTenant(btn) { openEntityModal('tenant', extractTenantData(btn), 'edit'); },
    viewUser(btn)    { openEntityModal('user',   extractUserData(btn),   'view'); },
    editUser(btn) { openEntityModal('user', extractUserData(btn), 'edit'); },
    viewViewing(btn) { openEntityModal('viewing', extractViewingData(btn), 'view'); },
    viewViewingNotes(btn) { openViewingNotes(btn); },
    editViewing(btn) { openEntityModal('viewing', extractViewingData(btn), 'edit'); },
    viewImportantDate(btn)    { openEntityModal('importantdate',   extractPropertyData(btn),   'view'); },
    editImportantDate(btn) { openEntityModal('importantdate', extractPropertyData(btn), 'edit'); },
    closeModal,
    switchTab,
    addNote,
    saveProperty,
    saveLandlord,
    saveTenant
  };

})();

function openFile(fileType) {
  alert(`Opening ${fileType.toUpperCase()} file... (Implement PDF later)`);
}

function fetchTenantHistory(tenantId) {
  const container = document.querySelector('.contant-row-t');
  if (!container) return;

  const header = container.querySelector('.data-row.header');
  container.innerHTML = '';
  if (header) container.appendChild(header);

  const loading = document.createElement('div');
  loading.className = 'data-row';
  loading.innerHTML = `<div class="cell" style="grid-column:1/-1;">
    <i class="fa-solid fa-spinner fa-spin"></i> Loading...
  </div>`;
  container.appendChild(loading);

  fetch(`get_tenant_history.php?tenant_id=${tenantId}`)
    .then(r => r.json())
    .then(history => {
      const header = container.querySelector('.data-row.header');
      container.innerHTML = '';
      if (header) container.appendChild(header);

      if (!history.length) {
        container.innerHTML += `<div class="data-row">
          <div class="cell" style="grid-column:1/-1;">No tenancy history found</div>
        </div>`;
        return;
      }

      history.forEach(h => {
        const row = document.createElement('div');
        row.className = 'data-row';

        const statusClass =
          h.status === 'current'  ? 'status-active' :
          h.status === 'upcoming' ? 'status-new' :
                                    'status-previous';

        const statusLabel =
          h.status === 'current'  ? 'Current' :
          h.status === 'upcoming' ? 'Upcoming' :
                                    'Previous';

        const safe = (s) => String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
        const fmtD = (d) => { if(!d) return '-'; const dt=new Date(d); return isNaN(dt)?d:dt.toLocaleDateString('en-GB'); };

        row.innerHTML = `
          <div class="cell">${safe(h.property_address)}</div>
          <div class="cell">${safe(h.landlord_name || '-')}</div>
          <div class="cell">${fmtD(h.contract_start)}</div>
          <div class="cell">${fmtD(h.contract_end)}</div>
          <div class="cell">
            <span class="status-badge ${statusClass}">${statusLabel}</span>
          </div>`;
        container.appendChild(row);
      });
    });
}
