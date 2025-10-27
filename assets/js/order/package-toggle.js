/**
 * Package Toggle Module
 * 
 * Handles the expand/collapse functionality for package data
 */

export function initPackageToggle($) {
    $(document).on('click', '.anar-package-data header', function(e) {
        e.preventDefault();
        $(this).parent('div').toggleClass('open');
    });
}
