// Đặt giao diện sáng/tối TRƯỚC khi vẽ trang để không bị nháy màu. Lựa chọn lưu ở trình duyệt.
(function () {
  var t = null;
  try { t = localStorage.getItem('pinpoint-theme'); } catch (e) { t = null; }
  if (t !== 'light' && t !== 'dark') {
    t = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  }
  document.documentElement.setAttribute('data-theme', t);
})();
