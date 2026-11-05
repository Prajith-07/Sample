// iTech Seed Grand Challenge 2027
// Client-side interactions & PHP/SQL API Integration

document.addEventListener('DOMContentLoaded', () => {
  console.log("iTech Seed Grand Challenge 2027 portal initialized with PHP & SQL backend.");

  // 1. Smooth scroll with sticky navbar offset
  const links = document.querySelectorAll('a[href^="#"]');
  const navbar = document.querySelector('.event-navbar');
  const navHeight = navbar ? navbar.offsetHeight + 24 : 80;

  links.forEach(link => {
    link.addEventListener('click', (e) => {
      const targetId = link.getAttribute('href');
      if (targetId && targetId !== '#') {
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          e.preventDefault();
          const elementPosition = targetElement.getBoundingClientRect().top + window.pageYOffset;
          const offsetPosition = elementPosition - navHeight;

          window.scrollTo({
            top: offsetPosition,
            behavior: 'smooth'
          });
        }
      }
    });
  });

  // 2. Interactive AJAX Registration Alerts (PHP + MySQL Backend)
  const subscribeForm = document.getElementById('subscribeForm');
  const feedbackEl = document.getElementById('formFeedback');
  const liveBadge = document.getElementById('liveSubscriberBadge');

  if (subscribeForm && feedbackEl) {
    subscribeForm.addEventListener('submit', async (e) => {
      e.preventDefault();

      const submitBtn = subscribeForm.querySelector('button[type="submit"]');
      const originalBtnText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>⏳</span> Registering...';

      feedbackEl.className = 'form-feedback-message';
      feedbackEl.style.display = 'none';

      try {
        const formData = new FormData(subscribeForm);
        const response = await fetch('api/subscribe.php', {
          method: 'POST',
          body: formData
        });

        const data = await response.json();

        feedbackEl.style.display = 'block';

        if (response.ok && data.success) {
          feedbackEl.className = 'form-feedback-message feedback-success';
          feedbackEl.innerHTML = data.message;
          subscribeForm.reset();

          // Increment counter dynamically if present
          if (liveBadge) {
            const currentNumMatch = liveBadge.innerHTML.match(/\d+/);
            if (currentNumMatch) {
              const newCount = parseInt(currentNumMatch[0], 10) + 1;
              liveBadge.innerHTML = `<strong>${newCount}</strong> Students on Alert List`;
            }
          }
        } else {
          feedbackEl.className = 'form-feedback-message feedback-error';
          feedbackEl.innerHTML = data.message || 'Unable to register at this time. Please try again.';
        }
      } catch (err) {
        feedbackEl.style.display = 'block';
        feedbackEl.className = 'form-feedback-message feedback-error';
        feedbackEl.innerHTML = 'Network or server error. Please ensure the local PHP/MySQL server is running.';
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalBtnText;
      }
    });
  }

  // 3. Interactive Wave Node Linking (Cards <-> SVG Wave Dots)
  const nodeItems = document.querySelectorAll('.wave-node-item');
  const waveDots = document.querySelectorAll('.wave-node-dot');

  nodeItems.forEach((item, index) => {
    const card = item.querySelector('.wave-card');
    const dot = waveDots[index];

    if (card && dot) {
      card.addEventListener('mouseenter', () => {
        dot.style.transform = 'scale(1.4)';
        dot.style.filter = 'drop-shadow(0 0 14px rgba(34, 197, 94, 0.95))';
      });
      card.addEventListener('mouseleave', () => {
        dot.style.transform = '';
        dot.style.filter = '';
      });

      dot.addEventListener('mouseenter', () => {
        card.style.transform = 'translateY(-6px)';
        card.style.borderColor = '#22c55e';
        card.style.boxShadow = '0 12px 28px rgba(22, 101, 52, 0.2)';
      });
      dot.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.borderColor = '';
        card.style.boxShadow = '';
      });
    }
  });
});
