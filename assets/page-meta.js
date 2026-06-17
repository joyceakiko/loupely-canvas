// Loupely Canvas - per page header and footer override toggle
//
// Shows the custom HTML box only when the matching "Use custom HTML" option is
// selected, for both the header and footer controls in the per page meta box.
// Moved out of an inline script in page-meta.php so behavior lives in assets,
// per the build rules. No values are passed from PHP; it reads the DOM only.

(function () {
  "use strict";

  var selects = document.querySelectorAll('#lc_header_footer select[data-lc-target]');

  selects.forEach(function (sel) {
    sel.addEventListener('change', function () {
      var wrap = document.getElementById(sel.getAttribute('data-lc-target'));
      if (wrap) {
        wrap.style.display = (sel.value === 'custom') ? '' : 'none';
      }
    });
  });
})();
