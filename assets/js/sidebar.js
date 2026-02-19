const sidebar = document.getElementById("sidebar");
const collapseBtn = document.getElementById("collapseBtn");
const toggle = document.getElementById("sidebarToggle");

if (collapseBtn) {
    collapseBtn.addEventListener("click", () => {
        sidebar.classList.toggle("collapsed");
        localStorage.setItem(
            "sidebarCollapsed",
            sidebar.classList.contains("collapsed")
        );
    });
}

if (toggle) {
    toggle.addEventListener("click", () => {
        sidebar.classList.toggle("active");
    });
}

/* Restore collapse state */
if (localStorage.getItem("sidebarCollapsed") === "true") {
    sidebar.classList.add("collapsed");
}
