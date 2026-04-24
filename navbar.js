function CallNavbar() {
  fetch("navbar.php", {
    credentials: "same-origin",
    headers: {
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then((response) => {
      if (!response.ok)
        throw new Error(`Navbar load failed: ${response.status}`);
      return response.text();
    })
    .then((data) => {
      document.getElementById("navbar-container").innerHTML = data;

      const currentPage = window.location.pathname.split("/").pop();
      const menuItems = document.querySelectorAll(".sidebar-menu li a");

      menuItems.forEach((item) => {
        const href = item.getAttribute("href");
        if (
          href === currentPage ||
          (currentPage === "" && href === "dashboard.php")
        ) {
          item.parentElement.classList.add("active");
        } else {
          item.parentElement.classList.remove("active");
        }
      });

      initSearch();
    })
    .catch((error) => console.error("Error loading navbar:", error));
}

function initSearch() {
  const input = document.getElementById("globalSearch");
  const wrapper = document.getElementById("searchBarWrapper");
  const btn = document.getElementById("searchBtn");
  if (!input || !wrapper) return;

  // Create dropdown element via JS to avoid any HTML escaping issues
  const dropdown = document.createElement("div");
  dropdown.id = "searchDropdown";
  dropdown.style.display = "none";
  dropdown.style.position = "absolute";
  dropdown.style.top = "calc(100% + 6px)";
  dropdown.style.left = "0";
  dropdown.style.right = "0";
  dropdown.style.background = "#fff";
  dropdown.style.border = "1px solid #ddd";
  dropdown.style.borderRadius = "8px";
  dropdown.style.boxShadow = "0 4px 16px rgba(0,0,0,0.12)";
  dropdown.style.zIndex = "9999";
  dropdown.style.maxHeight = "360px";
  dropdown.style.overflowY = "auto";
  wrapper.style.position = "relative";
  wrapper.appendChild(dropdown);

  const typeConfig = {
    property: {
      label: "Property",
      icon: "fa-house",
      color: "#1a73e8",
      bg: "#e8f4fd",
    },
    landlord: {
      label: "Landlord",
      icon: "fa-user-tie",
      color: "#e67e22",
      bg: "#fdf3e8",
    },
    tenant: {
      label: "Tenant",
      icon: "fa-user",
      color: "#27ae60",
      bg: "#e8fdf3",
    },
    viewing: {
      label: "Viewing",
      icon: "fa-street-view",
      color: "#c0392b",
      bg: "#fde8e8",
    },
  };

  let debounceTimer;

  input.addEventListener("input", function () {
    clearTimeout(debounceTimer);
    const q = this.value.trim();
    if (q.length < 2) {
      dropdown.style.display = "none";
      return;
    }
    debounceTimer = setTimeout(() => fetchResults(q), 200);
  });

  btn.addEventListener("click", function () {
    const q = input.value.trim();
    if (q.length >= 2) fetchResults(q);
  });

  document.addEventListener("click", function (e) {
    if (!wrapper.contains(e.target)) {
      dropdown.style.display = "none";
    }
  });

  function fetchResults(q) {
    fetch("search.php?q=" + encodeURIComponent(q))
      .then((r) => r.json())
      .then((results) => renderResults(results))
      .catch((err) => {
        console.error("Search error:", err);
        dropdown.style.display = "none";
      });
  }

  function renderResults(results) {
    dropdown.innerHTML = "";

    if (results.length === 0) {
      const empty = document.createElement("div");
      empty.textContent = "No results found";
      empty.style.padding = "14px 16px";
      empty.style.color = "#999";
      empty.style.fontSize = "0.9rem";
      dropdown.appendChild(empty);
      dropdown.style.display = "block";
      return;
    }

    results.forEach((r) => {
      const cfg = typeConfig[r.type] || {
        label: r.type,
        icon: "fa-circle",
        color: "#555",
        bg: "#eee",
      };

      const item = document.createElement("div");
      item.style.display = "flex";
      item.style.alignItems = "center";
      item.style.gap = "12px";
      item.style.padding = "10px 16px";
      item.style.cursor = "pointer";
      item.style.borderBottom = "1px solid #f0f0f0";

      const badge = document.createElement("span");
      badge.style.display = "inline-flex";
      badge.style.alignItems = "center";
      badge.style.gap = "5px";
      badge.style.padding = "3px 9px";
      badge.style.borderRadius = "20px";
      badge.style.fontSize = "0.75rem";
      badge.style.fontWeight = "600";
      badge.style.background = cfg.bg;
      badge.style.color = cfg.color;
      badge.style.whiteSpace = "nowrap";
      badge.innerHTML =
        '<i class="fa-solid ' + cfg.icon + '"></i> ' + cfg.label;

      const text = document.createElement("span");
      text.style.flex = "1";
      text.style.minWidth = "0";

      const labelEl = document.createElement("span");
      labelEl.textContent = r.label;
      labelEl.style.display = "block";
      labelEl.style.fontWeight = "500";
      labelEl.style.fontSize = "0.9rem";
      labelEl.style.whiteSpace = "nowrap";
      labelEl.style.overflow = "hidden";
      labelEl.style.textOverflow = "ellipsis";

      const subEl = document.createElement("span");
      subEl.textContent = r.sub || "";
      subEl.style.display = "block";
      subEl.style.fontSize = "0.78rem";
      subEl.style.color = "#999";

      text.appendChild(labelEl);
      text.appendChild(subEl);
      item.appendChild(badge);
      item.appendChild(text);

      item.addEventListener(
        "mouseenter",
        () => (item.style.background = "#f7f7f7"),
      );
      item.addEventListener("mouseleave", () => (item.style.background = ""));
      item.addEventListener("click", () => {
        window.location.href = r.url;
      });

      dropdown.appendChild(item);
    });

    dropdown.style.display = "block";
  }
}

function openProfileModal() {
  const modal = document.getElementById("profileModal");
  if (modal) {
    modal.classList.add("active");
    document.body.style.overflow = "hidden";
  } else {
    console.error("Profile modal not found in DOM");
  }
}

function closeProfileModal() {
  const modal = document.getElementById("profileModal");
  if (modal) {
    modal.classList.remove("active");
    document.body.style.overflow = "auto";
  }
}

window.CallNavbar = CallNavbar;


function resetMyPassword() {
    if (!confirm('Do You Want To Reset Your Password?')) return;

    fetch('reset_self_password.php', { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('New password sent to your email!');
                location.reload();
            } else {
                alert('Failed to send email. Please try again.');
            }
        })
        .catch(() => alert('Something went wrong.'));
}