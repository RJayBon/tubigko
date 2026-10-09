(function () {
  "use strict";

  var TRANSITIONS = {
    pending:          ["confirmed", "cancelled"],
    confirmed:        ["out_for_delivery", "cancelled"],
    out_for_delivery: ["delivered", "cancelled"]
  };

  var buttons = document.querySelectorAll("[data-open-delivery]");
  if (!buttons.length) return;

  buttons.forEach(function (btn) {
    btn.addEventListener("click", function () {
      var idEl    = document.getElementById("updateDeliveryId");
      var titleEl = document.getElementById("updateDeliveryTitle");
      var riderEl = document.getElementById("updateRider");
      var sel     = document.getElementById("updateStatus");

      if (idEl)    idEl.value = btn.dataset.id;
      if (titleEl) titleEl.textContent = "Update " + btn.dataset.code;
      if (riderEl) riderEl.value = btn.dataset.rider || "";

      var allowed = TRANSITIONS[btn.dataset.status] || [];
      if (sel) {
        Array.prototype.forEach.call(sel.options, function (opt) {
          var isAllowed = allowed.indexOf(opt.value) !== -1;
          opt.hidden = !isAllowed;
          opt.disabled = !isAllowed;
        });
        if (allowed.length) sel.value = allowed[0];
      }

      if (window.openModal) window.openModal("updateDeliveryModal");
    });
  });
})();