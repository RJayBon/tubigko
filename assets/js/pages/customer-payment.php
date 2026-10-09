(function () {
  "use strict";
  var form = document.getElementById("checkoutForm");
  var totalEl = document.getElementById("cartTotal");
  if (!form || !totalEl) return;

  var CART_KEY = "tubigko:cart";

  function cartSubtotal() {
    try {
      var cart = JSON.parse(localStorage.getItem(CART_KEY)) || [];
      return cart.reduce(function (s, c) {
        return s + Number(c.price) * Number(c.qty);
      }, 0);
    } catch (e) { return 0; }
  }

  function currentFee() {
    var checked = form.querySelector('input[name="payment_method"]:checked');
    return checked ? Number(checked.getAttribute("data-fee") || 0) : 0;
  }

  function refresh() {
    var total = cartSubtotal() + currentFee();
    totalEl.textContent = "\u20B1" + total.toLocaleString("en-PH", {
      minimumFractionDigits: 2, maximumFractionDigits: 2
    });
  }

  form.addEventListener("change", function (ev) {
    if (ev.target && ev.target.name === "payment_method") refresh();
  });

  refresh();
})();