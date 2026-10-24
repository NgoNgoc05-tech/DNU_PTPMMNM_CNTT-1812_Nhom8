// Kiểm tra dấu (750;150) trong 4 khung kích thước khác nhau và in ĐẠT/KHÔNG ĐẠT.
(function () {
  'use strict';
  var results = [];
  document.querySelectorAll('.calib-box').forEach(function (box) {
    var d = box.dataset;
    box.style.width = d.w + 'px';
    box.style.height = d.h + 'px';
    var view = new Annotator(box, {
      imageUrl: '/assets/calibration-1000x500.png', width: 1000, height: 500, editable: d.case === 'B', alt: 'Ảnh mẫu 1000×500',
      onDraft: function (pos) {
        document.getElementById('manual').textContent = 'Ảnh gốc: (' + pos.x + '; ' + pos.y + ') px — nằm trong ảnh.';
        view.setDraft(null);
      },
      onOutside: function () { document.getElementById('manual').textContent = 'Bấm vào lề trống: bị từ chối, không tạo toạ độ.'; }
    });
    view.setItems([{ id: 1, seq_no: 1, shape: 'POINT', x: 750, y: 150, status: 'OPEN' }]);
    if (Number(d.zoom) !== 1) { view.setZoom(Number(d.zoom)); }
    var pin = box.querySelector('.mark-pin');
    var b = box.getBoundingClientRect();
    var p = pin.getBoundingClientRect();
    var sx = p.left + p.width / 2 - b.left - box.clientLeft;
    var sy = p.top + p.height / 2 - b.top - box.clientTop;
    var back = PinCoords.toImage(sx, sy, view.content(), 1000, 500);
    var ok = Math.abs(sx - d.ex) <= 0.5 && Math.abs(sy - d.ey) <= 0.5 && Math.abs(back.x - 750) <= 0.5 && Math.abs(back.y - 150) <= 0.5;
    results.push({ id: d.case, ok: ok, sx: sx, sy: sy });
    var out = document.querySelector('[data-result="' + d.case + '"]');
    out.textContent = '';
    var verdict = document.createElement('span');
    verdict.className = ok ? 'pass' : 'fail';
    verdict.textContent = ok ? 'ĐẠT' : 'KHÔNG ĐẠT';
    out.appendChild(verdict);
    out.appendChild(document.createTextNode(' · màn hình (' + sx.toFixed(1) + '; ' + sy.toFixed(1) + '), mong đợi (' + d.ex + '; ' + d.ey +
      ') · đổi ngược = (' + back.x.toFixed(1) + '; ' + back.y.toFixed(1) + ')'));
  });
  window.calibrationResults = results;
})();
