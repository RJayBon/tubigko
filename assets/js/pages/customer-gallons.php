(function () {
  "use strict";
  var filter = document.getElementById("typeFilter");
  var grid   = document.getElementById("gallonGrid");
  if (!filter || !grid) return;

  filter.addEventListener("change", function () {
    var v = filter.value;
    grid.querySelectorAll(".gcard").forEach(function (c) {
      c.hidden = v && c.getAttribute("data-type") !== v;
    });
  });
})();