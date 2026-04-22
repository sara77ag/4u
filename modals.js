// Modal configuration
const modalConfig = {
  property: {
    form: "addPropertyForm",
    modal: "addPropertyModal",
    hasFiles: false,
  },
  landlord: {
    form: "addLandlordForm",
    modal: "addLandlordModal",
    hasFiles: true,
    filesKey: "landlordFiles",
  },
  tenant: {
    form: "addTenantForm",
    modal: "addTenantModal",
    hasFiles: true,
    filesKey: "tenantFiles",
  },
  viewing: {
    form: "bookViewingForm",
    modal: "bookViewingModal",
    hasFiles: false,
  },
  payment: {
    form: "addPaymentForm",
    modal: "addPaymentModal",
    hasFiles: true,
    filesKey: "paymentFiles",
  },
  contract: {
    form: "addContractForm",
    modal: "addContractModal",
    hasFiles: true,
    filesKey: "contractFiles",
  },
  user: { form: "addUserForm", modal: "addUserModal", hasFiles: false },
  uploadFile: {
    form: "uploadFileForm",
    modal: "uploadFileModal",
    hasFiles: true,
  },
};

const fileStorage = {};

function openUploadFileModal() {
  openModal("uploadFile");
}
function closeUploadFileModal() {
  closeModal("uploadFile");
}
function resetUploadFileForm() {
  resetForm("uploadFile");
}

function openModal(type) {
  const config = modalConfig[type];
  if (config) {
    document.getElementById(config.modal).classList.add("active");
    document.body.style.overflow = "hidden";
  }
}

function closeModal(type) {
  const config = modalConfig[type];
  if (config) {
    document.getElementById(config.modal).classList.remove("active");
    document.body.style.overflow = "auto";
  }
}

function resetForm(type) {
  const config = modalConfig[type];
  if (config) {
    document.getElementById(config.form).reset();
    if (config.hasFiles) {
      fileStorage[config.filesKey] = [];
      updateFileDisplay(type, fileStorage[config.filesKey]);
    }
  }
}

function saveForm(type, successMessage) {
  const config = modalConfig[type];
  const form = document.getElementById(config.form);
  if (form.checkValidity()) {
    alert(successMessage);
    closeModal(type);
    resetForm(type);
  } else {
    form.reportValidity();
  }
}

function handleFiles(type, files) {
  const config = modalConfig[type];
  if (!config || !config.hasFiles) return;

  const key = config.filesKey;
  if (!fileStorage[key]) fileStorage[key] = [];

  const allowedTypes = [
    "application/pdf",
    "application/msword",
    "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    "image/png",
    "image/jpeg",
  ];

  for (let i = 0; i < files.length; i++) {
    if (allowedTypes.includes(files[i].type)) {
      fileStorage[key].push(files[i]);
    } else {
      alert("Only PDF, Word, or image files are allowed.");
    }
  }
  updateFileDisplay(type, fileStorage[key]);
}

function removeFile(type, index) {
  const config = modalConfig[type];
  if (!config || !config.hasFiles) return;

  const key = config.filesKey;
  fileStorage[key].splice(index, 1);
  updateFileDisplay(type, fileStorage[key]);
}

function updateFileDisplay(type, filesArray) {
  const fileCountEl = document.getElementById(type + "FileCount");
  const fileListEl = document.getElementById(type + "FileList");

  if (!fileCountEl || !fileListEl) return;

  fileCountEl.innerHTML =
    filesArray.length > 0
      ? '<span class="file-count-badge">' +
        filesArray.length +
        " file(s) uploaded</span>"
      : "";

  fileListEl.innerHTML = "";
  filesArray.forEach((file, index) => {
    const fileItem = document.createElement("div");
    fileItem.className = "uploaded-file";
    fileItem.innerHTML =
      '<i class="fa-solid fa-file-pdf"></i><span>' +
      file.name +
      "</span>" +
      '<button type="button" class="delete-file" onclick="removeFile(\'' +
      type +
      "'," +
      index +
      ')">' +
      '<i class="fa-solid fa-times"></i></button>';
    fileListEl.appendChild(fileItem);
  });
}

function saveProperty() {
  saveForm("property", "Property saved successfully!");
}
function saveLandlord() {
  saveForm("landlord", "Landlord saved successfully!");
}
function saveTenant() {
  saveForm("tenant", "Tenant saved successfully!");
}
function saveViewing() {
  saveForm("viewing", "Viewing booked successfully!");
}
function savePayment() {
  saveForm("payment", "Payment saved successfully!");
}
function saveContract() {
  saveForm("contract", "Contract created successfully!");
}
function saveUser() {
  saveForm("user", "User added successfully!");
}

function openAddPropertyModal() {
  openModal("property");
}
function openAddLandlordModal() {
  openModal("landlord");
}
function openAddTenantModal() {
  openModal("tenant");
}
function openBookViewingModal() {
  openModal("viewing");
}
function openAddPaymentModal() {
  openModal("payment");
}
function openAddContractModal() {
  openModal("contract");
}
function openAddUserModal() {
  openModal("user");
}

function closeAddPropertyModal() {
  closeModal("property");
}
function closeAddLandlordModal() {
  closeModal("landlord");
}
function closeAddTenantModal() {
  closeModal("tenant");
}
function closeBookViewingModal() {
  closeModal("viewing");
}
function closeAddPaymentModal() {
  closeModal("payment");
}
function closeAddContractModal() {
  closeModal("contract");
}
function closeAddUserModal() {
  closeModal("user");
}

function resetAddPropertyForm() {
  resetForm("property");
}
function resetAddLandlordForm() {
  resetForm("landlord");
}
function resetAddTenantForm() {
  resetForm("tenant");
}
function resetBookViewingForm() {
  resetForm("viewing");
}
function resetAddPaymentForm() {
  resetForm("payment");
}
function resetAddContractForm() {
  resetForm("contract");
}
function resetAddUserForm() {
  resetForm("user");
}

let currentSort = {
  column: null,
  direction: "asc",
};

function toggleFilter() {
  const dropdowns = document.getElementById("filterDropdowns");
  const btn = document.getElementById("filterToggle");

  if (dropdowns.style.display === "none") {
    dropdowns.style.display = "flex";
    btn.classList.add("active");
  } else {
    dropdowns.style.display = "none";
    btn.classList.remove("active");
  }
}

function loadModals() {
  const modalsContainer = document.getElementById("modals-container");
  if (modalsContainer) {
    const xhr = new XMLHttpRequest();
    xhr.open("GET", "modals.php", true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4 && xhr.status === 200) {
        modalsContainer.innerHTML = xhr.responseText;
        if (typeof afterModalsLoaded === "function") {
          afterModalsLoaded();
        }
      }
    };
    xhr.send();
  }
}