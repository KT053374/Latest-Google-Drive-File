document.addEventListener('DOMContentLoaded', function () {
    var select = document.querySelector('select[name="dln_folder_count"]');
    var cards  = document.querySelectorAll('.dln-folder-card');

    if (!select || !cards.length) {
        return;
    }

    function updateFolderCardVisibility() {
        var value = parseInt(select.value, 10);
        if (isNaN(value) || value < 1) value = 1;
        if (value > 10) value = 10;

        cards.forEach(function (card) {
            var slot = parseInt(card.getAttribute('data-slot'), 10);
            if (!slot) {
                card.style.display = 'none';
                return;
            }
            if (slot <= value) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }

    select.addEventListener('change', updateFolderCardVisibility);

    // initial apply
    updateFolderCardVisibility();
});