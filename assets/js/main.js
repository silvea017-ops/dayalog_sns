// Optional client-side features
document.addEventListener('DOMContentLoaded', function(){
  // keep theme in sync between cookie and data-theme attribute (best-effort)
  const theme = document.documentElement.getAttribute('data-theme');
  if (theme) {
    document.documentElement.dataset.theme = theme;
  }
});