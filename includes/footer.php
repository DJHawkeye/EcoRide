<footer style="background-color: #2a6f4a;" class="text-light py-3">
  <div class="container d-flex justify-content-between align-items-center flex-wrap">

    <!-- Left: Social media icons linked to homepage -->
    <div class="social-icons d-flex gap-3">
      <a href="index.php" class="text-light" aria-label="Facebook" title="Facebook">
        <i class="fab fa-facebook-f fa-lg"></i>
      </a>
      <a href="index.php" class="text-light" aria-label="Twitter" title="Twitter">
        <i class="fab fa-twitter fa-lg"></i>
      </a>
      <a href="index.php" class="text-light" aria-label="Instagram" title="Instagram">
        <i class="fab fa-instagram fa-lg"></i>
      </a>
      <a href="index.php" class="text-light" aria-label="LinkedIn" title="LinkedIn">
        <i class="fab fa-linkedin-in fa-lg"></i>
      </a>
    </div>

    <!-- Center: Email address -->
    <div class="footer-email text-center flex-grow-1">
      <a href="contact.php" class="text-light fw-semibold" style="text-decoration: none;">
        contact@ecoride.fr
      </a>
    </div>

    <!-- Right: Mentions légales -->
    <div class="footer-legal">
      <a href="mentions-legales.php" class="text-light text-decoration-none">
        Mentions légales
      </a>
    </div>

  </div>
</footer>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const bgImages = [
    'images/background/bg1.jpg',
    'images/background/bg2.jpg',
    'images/background/bg3.jpg',
    'images/background/bg4.jpg',
    'images/background/bg5.jpg',
    'images/background/bg6.jpg',
    'images/background/bg7.jpg',
    'images/background/bg8.jpg',
    'images/background/bg9.jpg',
    'images/background/bg10.jpg'
  ];

  const bg1 = document.getElementById('bg1');
  const bg2 = document.getElementById('bg2');

  let index = 0;
  let visibleDiv = bg1;

  visibleDiv.style.backgroundImage = `url(${bgImages[index]})`;
  visibleDiv.classList.add('visible');

  function changeBackground() {
    index = (index + 1) % bgImages.length;
    const hiddenDiv = (visibleDiv === bg1) ? bg2 : bg1;

    hiddenDiv.style.backgroundImage = `url(${bgImages[index]})`;
    hiddenDiv.classList.add('visible');
    visibleDiv.classList.remove('visible');
    visibleDiv = hiddenDiv;
  }

  setInterval(changeBackground, 6000);
</script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const modal = document.getElementById('contactModal');
  modal.addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const username = button.getAttribute('data-username');
    const email = button.getAttribute('data-email');
    const subject = button.getAttribute('data-subject');

    modal.querySelector('#to_name').value = username;
    modal.querySelector('#to_email').value = email;
    modal.querySelector('#subject').value = subject;
  });
});
</script>

</body>
</html>
