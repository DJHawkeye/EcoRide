<?php
// includes/js_helpers.php

function echoVehicleSeatsScript(): void {
    echo <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const vehicleSelect = document.getElementById('vehicle_id');
    const seatsInput = document.getElementById('seats_available');

    function updateVehicleSeats() {
      const selectedOption = vehicleSelect.options[vehicleSelect.selectedIndex];
      const seats = selectedOption.getAttribute('data-seats');
      if (seats && (!seatsInput.value || seatsInput.value <= 0)) {
        seatsInput.value = seats;
      }
    }

    vehicleSelect.addEventListener('change', updateVehicleSeats);
    updateVehicleSeats();
  });
</script>
JS;
}


function echoCustomPrefsScript(): void {
    echo <<<JS
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.getElementById('addPrefBtn').addEventListener('click', function () {
      const container = document.getElementById('customPrefsContainer');
      const row = document.createElement('div');
      row.className = 'custom-pref-row mb-2 d-flex gap-2';
      row.innerHTML =
        '<input type="text" name="custom_prefs[]" placeholder="Préférence (ex: calme, pas d’animaux)" class="form-control" />' +
        '<button type="button" class="btn btn-outline-danger btn-sm remove-pref">×</button>';
      container.appendChild(row);
    });

    document.getElementById('customPrefsContainer').addEventListener('click', function (e) {
      if (e.target.classList.contains('remove-pref')) {
        e.target.closest('.custom-pref-row').remove();
      }
    });
  });
</script>
JS;
}

function initCustomPreferencesEditor(string $addButtonId, string $containerId): void {
    echo <<<JS
<script>
  const container = document.getElementById('$containerId');
  const addBtn = document.getElementById('$addButtonId');

  addBtn.addEventListener('click', () => {
    const row = document.createElement('div');
    row.className = 'custom-pref-row mb-2 d-flex gap-2';
    row.innerHTML = `
      <input type="text" name="custom_prefs[]" placeholder="Préférence (ex: calme, pas d’animaux)" class="form-control" />
      <button type="button" class="btn btn-outline-danger btn-sm remove-pref">×</button>
    `;
    container.appendChild(row);
  });

  container.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-pref')) {
      e.target.closest('.custom-pref-row').remove();
    }
  });
</script>
JS;
}



function initReviewCarousel(array $reviews, string $containerId = 'review-content', string $counterId = 'review-counter', string $prevBtnId = 'prev-review', string $nextBtnId = 'next-review'): void {
    // Safely encode the reviews into a JS variable
    $encodedReviews = htmlspecialchars(json_encode($reviews, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8');

    echo <<<JS
<script>
document.addEventListener("DOMContentLoaded", function() {
    const reviews = JSON.parse("$encodedReviews");
    let currentIndex = 0;

    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, m => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[m]));
    }

    function formatDate(dateStr) {
        const d = new Date(dateStr);
        return d.toLocaleDateString('fr-FR', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    }

    function renderReview(idx) {
        const r = reviews[idx];
        const container = document.getElementById("$containerId");
        const counter = document.getElementById("$counterId");
        const prevBtn = document.getElementById("$prevBtnId");
        const nextBtn = document.getElementById("$nextBtnId");

        if (!container || !counter || !prevBtn || !nextBtn) return;

        container.innerHTML = `
            <strong>\${escapeHtml(r.passenger_name)}</strong>
            <span> – \${formatDate(r.created_at)}</span>
            <div>Note : \${r.rating} ⭐</div>
            <p id="review-comment">\${escapeHtml(r.comment)}</p>
        `;
        counter.textContent = \`\${idx + 1} / \${reviews.length}\`;
        prevBtn.disabled = (idx === 0);
        nextBtn.disabled = (idx === reviews.length - 1);
    }

    const prevBtn = document.getElementById("$prevBtnId");
    const nextBtn = document.getElementById("$nextBtnId");

    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentIndex > 0) renderReview(--currentIndex);
        });

        nextBtn.addEventListener('click', () => {
            if (currentIndex < reviews.length - 1) renderReview(++currentIndex);
        });
    }

    if (reviews.length > 0) {
        renderReview(0);
    }
});
</script>
JS;
}

