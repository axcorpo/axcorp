// Swicher
function toggleSwitcher() {
    var i = document.getElementById('style-switcher');
    var currentLeft = window.getComputedStyle(i).left;

    if (currentLeft === "-189px") {
        i.style.left = "0px";
    } else {
        i.style.left = "-189px";
    }
}

function setColor(theme) {
    document.getElementById('color-opt').href = window.location.origin + './css/colors/' + theme + '.css';
    toggleSwitcher(false);
}

function setTheme(theme) {
    document.getElementById('theme-opt').href = window.location.origin + './css/' + theme + '.css';
    toggleSwitcher(false);
}