document.addEventListener('DOMContentLoaded', function() {
  console.log('Trader JS loaded');
  
  setTimeout(function() {
    var el = document.querySelector('.success-message');
    if(el) el.style.display = 'none';
  }, 3000);
});
