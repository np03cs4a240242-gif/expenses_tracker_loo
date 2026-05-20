// public/assets/js/app.js

(function () {
  "use strict";

  function setupConfirmForms() {
    document.addEventListener("submit", function (event) {
      const form = event.target;

      if (!form || !form.matches("form[data-confirm]")) {
        return;
      }

      const message = form.getAttribute("data-confirm") || "Are you sure?";
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  }

  function setupResendCountdown() {
    const button = document.querySelector("[data-resend-seconds]");
    if (!button) {
      return;
    }

    let seconds = Number(button.getAttribute("data-resend-seconds") || 0);
    const readyLabel = button.getAttribute("data-resend-label") || "Resend code";

    function updateButton() {
      if (seconds > 0) {
        button.disabled = true;
        button.textContent = "Resend in " + seconds + "s";
        return;
      }

      button.disabled = false;
      button.textContent = readyLabel;
    }

    updateButton();

    if (seconds <= 0) {
      return;
    }

    const timer = window.setInterval(function () {
      seconds -= 1;
      updateButton();

      if (seconds <= 0) {
        window.clearInterval(timer);
      }
    }, 1000);
  }

  function setupReportsCharts() {
    if (typeof window.__REPORTS__ === "undefined" || typeof Chart === "undefined") {
      return;
    }

    const categoryChart = document.getElementById("catChart");
    if (categoryChart) {
      new Chart(categoryChart, {
        type: "doughnut",
        data: {
          labels: window.__REPORTS__.cat.labels,
          datasets: [{ data: window.__REPORTS__.cat.totals }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { position: "bottom" },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return context.label + ": Rs. " + Number(context.parsed).toFixed(2);
                }
              }
            }
          }
        }
      });
    }

    const monthChart = document.getElementById("monthChart");
    if (monthChart) {
      new Chart(monthChart, {
        type: "bar",
        data: {
          labels: window.__REPORTS__.month.labels,
          datasets: [{ data: window.__REPORTS__.month.totals }]
        },
        options: {
          responsive: true,
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (context) {
                  return "Rs. " + Number(context.parsed.y).toFixed(2);
                }
              }
            }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: {
                callback: function (value) {
                  return "Rs. " + value;
                }
              }
            }
          }
        }
      });
    }
  }

  function setupSearchAutocomplete() {
    const searchInput = document.getElementById("smart-search");
    if (!searchInput) return;

    const dropdown = document.getElementById("search-dropdown");
    if (!dropdown) return;

    const baseUrl = searchInput.getAttribute("data-api-url") || "/expense-tracker/public/api/search_suggestions.php";
    let debounceTimer = null;
    let selectedIndex = -1;
    let currentSuggestions = [];

    function showDropdown(suggestions) {
      currentSuggestions = suggestions;
      selectedIndex = -1;
      dropdown.innerHTML = "";

      if (!suggestions || suggestions.length === 0) {
        dropdown.classList.remove("show");
        return;
      }

      suggestions.forEach(function (item, index) {
        const div = document.createElement("div");
        div.className = "search-suggestion-item";
        div.setAttribute("data-index", index);
        div.setAttribute("data-value", item.value);

        const icon = document.createElement("span");
        icon.className = "search-suggestion-icon";
        icon.textContent = getIconForType(item.icon);

        const label = document.createElement("span");
        label.className = "search-suggestion-label";
        label.textContent = item.label;

        const type = document.createElement("span");
        type.className = "search-suggestion-type";
        type.textContent = getTypeLabel(item.type);

        div.appendChild(icon);
        div.appendChild(label);
        div.appendChild(type);

        div.addEventListener("click", function () {
          applySuggestion(item);
        });

        div.addEventListener("mouseenter", function () {
          clearSelection();
          selectedIndex = index;
          div.classList.add("selected");
        });

        dropdown.appendChild(div);
      });

      dropdown.classList.add("show");
    }

    function hideDropdown() {
      dropdown.classList.remove("show");
      currentSuggestions = [];
      selectedIndex = -1;
    }

    function clearSelection() {
      dropdown.querySelectorAll(".search-suggestion-item").forEach(function (el) {
        el.classList.remove("selected");
      });
    }

    function applySuggestion(item) {
      searchInput.value = item.value;
      hideDropdown();
      searchInput.focus();
    }

    function getIconForType(icon) {
      const icons = {
        "calendar": "📅",
        "tag": "🏷️",
        "clock": "🕐",
        "note": "📝",
        "payment": "💳",
        "amount": "💰",
      };
      return icons[icon] || "🔍";
    }

    function getTypeLabel(type) {
      const labels = {
        "category": "Category",
        "note": "Note",
        "shortcut": "Quick",
        "recent": "Recent",
      };
      return labels[type] || "";
    }

    function fetchSuggestions(query) {
      const url = baseUrl + "?q=" + encodeURIComponent(query);
      fetch(url, { headers: { "Accept": "application/json" } })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.success && data.data && data.data.suggestions) {
            showDropdown(data.data.suggestions);
          } else {
            hideDropdown();
          }
        })
        .catch(function () {
          hideDropdown();
        });
    }

    searchInput.addEventListener("input", function () {
      clearTimeout(debounceTimer);
      const query = searchInput.value.trim();

      if (query.length === 0) {
        fetchSuggestions("");
        return;
      }

      debounceTimer = setTimeout(function () {
        fetchSuggestions(query);
      }, 200);
    });

    searchInput.addEventListener("focus", function () {
      const query = searchInput.value.trim();
      if (query.length === 0) {
        fetchSuggestions("");
      } else {
        fetchSuggestions(query);
      }
    });

    searchInput.addEventListener("keydown", function (e) {
      if (!dropdown.classList.contains("show")) return;

      const items = dropdown.querySelectorAll(".search-suggestion-item");
      if (!items.length) return;

      if (e.key === "ArrowDown") {
        e.preventDefault();
        clearSelection();
        selectedIndex = (selectedIndex + 1) % items.length;
        items[selectedIndex].classList.add("selected");
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        clearSelection();
        selectedIndex = selectedIndex <= 0 ? items.length - 1 : selectedIndex - 1;
        items[selectedIndex].classList.add("selected");
      } else if (e.key === "Enter" && selectedIndex >= 0) {
        e.preventDefault();
        const item = currentSuggestions[selectedIndex];
        if (item) applySuggestion(item);
      } else if (e.key === "Escape") {
        hideDropdown();
      }
    });

    document.addEventListener("click", function (e) {
      if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
        hideDropdown();
      }
    });

    // Save search on form submit
    const searchForm = searchInput.closest("form");
    if (searchForm) {
      searchForm.addEventListener("submit", function () {
        const query = searchInput.value.trim();
        if (query) {
          // Store recent searches in session via hidden field
          let recent = searchInput.getAttribute("data-recent") || "";
          const parts = recent ? recent.split("|").filter(Boolean) : [];
          parts.unshift(query);
          // Keep only last 10
          const unique = [];
          const seen = {};
          for (let i = 0; i < parts.length; i++) {
            if (!seen[parts[i]]) {
              seen[parts[i]] = true;
              unique.push(parts[i]);
            }
          }
          searchInput.setAttribute("data-recent", unique.slice(0, 10).join("|"));
        }
      });
    }
  }

  document.addEventListener("DOMContentLoaded", function () {
    setupResendCountdown();
    setupReportsCharts();
    setupSearchAutocomplete();
  });

  setupConfirmForms();
})();
