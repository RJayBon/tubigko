(function () {
  "use strict";
  document.querySelectorAll("[data-open-gallon-edit]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var idEl    = document.getElementById("editGallonId");
      var priceEl = document.getElementById("editPrice");
      var stockEl = document.getElementById("editStock");
      if (idEl)    idEl.value    = btn.dataset.id;
      if (priceEl) priceEl.value = btn.dataset.price;
      if (stockEl) stockEl.value = btn.dataset.stock;
      if (window.openModal) window.openModal("editGallonModal");
    });
  });
})();