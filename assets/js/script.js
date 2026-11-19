
// Confirm before deleting a record
function confirmDelete() {
    return confirm("Are you sure you want to delete this record?");
}

// Automatically hide success and error messages
document.addEventListener("DOMContentLoaded", function () {
    const alerts = document.querySelectorAll(".alert");

    alerts.forEach(function (alert) {
        setTimeout(function () {
            alert.style.transition = "opacity 0.5s";
            alert.style.opacity = "0";

            setTimeout(function () {
                alert.remove();
            }, 500);
        }, 3500);
    });
});

document.querySelectorAll('.custom-alert').forEach(function(alert) {
    setTimeout(function() {
        const instance = bootstrap.Alert.getOrCreateInstance(alert);
        instance.close();
    }, 4000);
});


// Dark / Light Mode
document.addEventListener('DOMContentLoaded', function () {

    const themeButton = document.getElementById('themeToggle');
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');

    function updateThemeButton(theme) {
        if (!themeButton) return;

        if (theme === 'dark') {
            themeIcon.textContent = '☀️';
            themeText.textContent = 'Light Mode';
        } else {
            themeIcon.textContent = '🌙';
            themeText.textContent = 'Dark Mode';
        }
    }

    const currentTheme =
        document.documentElement.getAttribute('data-theme') || 'light';

    updateThemeButton(currentTheme);

    if (themeButton) {
        themeButton.addEventListener('click', function () {

            const current =
                document.documentElement.getAttribute('data-theme');

            const newTheme = current === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('appTheme', newTheme);

            updateThemeButton(newTheme);
        });
    }
});
