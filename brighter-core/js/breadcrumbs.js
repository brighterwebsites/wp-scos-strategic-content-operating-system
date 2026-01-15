/**
 * Breadcrumb Overflow Detection
 * Adds .is-overflowing class to wrapper when breadcrumbs exceed container width
 */
(function() {
    function checkBreadcrumbOverflow() {
        var wrappers = document.querySelectorAll('.bw-breadcrumbs-wrap');
        
        wrappers.forEach(function(wrapper) {
            var breadcrumbs = wrapper.querySelector('.bw-breadcrumbs');
            if (!breadcrumbs) return;
            
            if (breadcrumbs.scrollWidth > breadcrumbs.clientWidth) {
                wrapper.classList.add('is-overflowing');
            } else {
                wrapper.classList.remove('is-overflowing');
            }
        });
    }
    
    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkBreadcrumbOverflow);
    } else {
        checkBreadcrumbOverflow();
    }
    
    // Re-check on resize
    window.addEventListener('resize', checkBreadcrumbOverflow);
})();


