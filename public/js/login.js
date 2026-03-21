    document.addEventListener('DOMContentLoaded', function() {
      const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
      const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
      if (sidebarWrapper && typeof OverlayScrollbarsGlobal !== 'undefined') {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: {
            theme: 'os-theme-light',
            autoHide: 'leave',
            clickScroll: true,
          },
        });
      }
    });