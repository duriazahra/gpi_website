/**
 * Govt Polytechnic Institute (GPI) - Official JavaScript
 * Pure Vanilla JS Implementation (No Frameworks or External Libraries)
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileNavigation();
  initStickyHeader();
  initActiveNavLink();
  initCounterAnimations();
  initScrollReveals();
  initBackToTop();
  initAdmissionForm();
  initContactForm();
  initGallery();
  initAnnouncementsModal();
});

/* --------------------------------------------------------------------------
   1. Mobile Navigation & Drawer
   -------------------------------------------------------------------------- */
function initMobileNavigation() {
  const toggleBtn = document.querySelector('.mobile-toggle');
  const drawer = document.querySelector('.mobile-nav-drawer');
  const overlay = document.querySelector('.mobile-nav-overlay');
  const closeBtn = document.querySelector('.mobile-drawer-close');
  const drawerLinks = document.querySelectorAll('.mobile-nav-links .nav-link');

  if (!toggleBtn || !drawer || !overlay) return;

  function openDrawer() {
    drawer.classList.add('is-active');
    overlay.classList.add('is-active');
    toggleBtn.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
    if (closeBtn) closeBtn.focus();
  }

  function closeDrawer() {
    drawer.classList.remove('is-active');
    overlay.classList.remove('is-active');
    toggleBtn.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  }

  toggleBtn.addEventListener('click', openDrawer);
  if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);

  // Close when navigation link is clicked
  drawerLinks.forEach(link => {
    link.addEventListener('click', closeDrawer);
  });

  // Keyboard accessibility
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && drawer.classList.contains('is-active')) {
      closeDrawer();
      toggleBtn.focus();
    }
  });
}

/* --------------------------------------------------------------------------
   2. Sticky Header
   -------------------------------------------------------------------------- */
function initStickyHeader() {
  const header = document.querySelector('.site-header');
  if (!header) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 40) {
      header.classList.add('is-scrolled');
    } else {
      header.classList.remove('is-scrolled');
    }
  }, { passive: true });
}

/* --------------------------------------------------------------------------
   3. Active Navigation State Detection
   -------------------------------------------------------------------------- */
function initActiveNavLink() {
  const currentPath = window.location.pathname.split('/').pop() || 'index.html';
  const navLinks = document.querySelectorAll('.nav-link');

  navLinks.forEach(link => {
    const href = link.getAttribute('href');
    if (!href) return;
    
    // Normalize comparison
    const linkPath = href.split('/').pop();
    if (linkPath === currentPath || (currentPath === '' && linkPath === 'index.html')) {
      link.classList.add('active');
    } else {
      link.classList.remove('active');
    }
  });
}

/* --------------------------------------------------------------------------
   4. Animated Statistics Counters
   -------------------------------------------------------------------------- */
function initCounterAnimations() {
  const statCards = document.querySelectorAll('.stat-number-wrap[data-target]');
  if (!statCards.length) return;

  let hasAnimated = false;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting && !hasAnimated) {
        hasAnimated = true;
        statCards.forEach(counter => {
          const target = parseInt(counter.getAttribute('data-target'), 10);
          const suffix = counter.getAttribute('data-suffix') || '';
          const duration = 1800; // ms
          const stepTime = 20;
          const totalSteps = duration / stepTime;
          const stepIncrement = target / totalSteps;
          let currentVal = 0;

          const timer = setInterval(() => {
            currentVal += stepIncrement;
            if (currentVal >= target) {
              counter.textContent = target.toLocaleString() + suffix;
              clearInterval(timer);
            } else {
              counter.textContent = Math.floor(currentVal).toLocaleString() + suffix;
            }
          }, stepTime);
        });
      }
    });
  }, { threshold: 0.3 });

  const statsSection = document.querySelector('.stats-section');
  if (statsSection) {
    observer.observe(statsSection);
  }
}

/* --------------------------------------------------------------------------
   5. Scroll Reveal Animations
   -------------------------------------------------------------------------- */
function initScrollReveals() {
  const revealElements = document.querySelectorAll('.reveal-on-scroll');
  if (!revealElements.length) return;

  // Check if reduced motion is preferred
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (prefersReducedMotion) {
    revealElements.forEach(el => el.classList.add('is-revealed'));
    return;
  }

  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

  revealElements.forEach(el => observer.observe(el));
}

/* --------------------------------------------------------------------------
   6. Back to Top Button
   -------------------------------------------------------------------------- */
function initBackToTop() {
  const backToTopBtn = document.querySelector('.back-to-top');
  if (!backToTopBtn) return;

  window.addEventListener('scroll', () => {
    if (window.scrollY > 350) {
      backToTopBtn.classList.add('is-visible');
    } else {
      backToTopBtn.classList.remove('is-visible');
    }
  }, { passive: true });

  backToTopBtn.addEventListener('click', () => {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  });
}

/* --------------------------------------------------------------------------
   7. Admission Application Form Engine
   -------------------------------------------------------------------------- */
function initAdmissionForm() {
  const form = document.getElementById('admissionForm');
  if (!form) return;

  // Total and Obtained Marks Auto Percentage Calculator
  const totalMarksInput = document.getElementById('totalMarks');
  const obtainedMarksInput = document.getElementById('obtainedMarks');
  const percentageInput = document.getElementById('percentage');

  function calculatePercentage() {
    const total = parseFloat(totalMarksInput.value);
    const obtained = parseFloat(obtainedMarksInput.value);

    if (!isNaN(total) && !isNaN(obtained) && total > 0) {
      if (obtained > total) {
        setFieldError(obtainedMarksInput, 'Obtained marks cannot exceed total marks');
        if (percentageInput) percentageInput.value = '';
      } else {
        clearFieldError(obtainedMarksInput);
        const percent = ((obtained / total) * 100).toFixed(2);
        if (percentageInput) {
          percentageInput.value = percent + '%';
          clearFieldError(percentageInput);
        }
      }
    }
  }

  if (totalMarksInput && obtainedMarksInput) {
    totalMarksInput.addEventListener('input', calculatePercentage);
    obtainedMarksInput.addEventListener('input', calculatePercentage);
  }

  // File Upload Preview UI
  const fileInputs = form.querySelectorAll('input[type="file"]');
  fileInputs.forEach(input => {
    input.addEventListener('change', (e) => {
      const file = e.target.files[0];
      const previewEl = input.closest('.file-upload-box').querySelector('.file-upload-name');
      if (file && previewEl) {
        const sizeMb = (file.size / (1024 * 1024)).toFixed(2);
        if (file.size > 5 * 1024 * 1024) {
          previewEl.textContent = `❌ File too large (${sizeMb}MB). Max limit is 5MB.`;
          previewEl.style.color = 'var(--danger-500)';
          input.value = '';
        } else {
          previewEl.textContent = `✓ Selected: ${file.name} (${sizeMb} MB)`;
          previewEl.style.color = 'var(--success-600)';
        }
      }
    });
  });

  // Preselect program if specified in URL query string (e.g. ?program=electrical)
  const urlParams = new URLSearchParams(window.location.search);
  const programParam = urlParams.get('program');
  const programSelect = document.getElementById('selectedProgram');
  if (programParam && programSelect) {
    for (let i = 0; i < programSelect.options.length; i++) {
      if (programSelect.options[i].value.toLowerCase().includes(programParam.toLowerCase())) {
        programSelect.selectedIndex = i;
        break;
      }
    }
  }

  // CNIC Auto-Formatting helper
  const cnicInput = document.getElementById('cnicNumber');
  if (cnicInput) {
    cnicInput.addEventListener('input', (e) => {
      let val = e.target.value.replace(/\D/g, '');
      if (val.length > 13) val = val.substring(0, 13);
      if (val.length > 12) {
        e.target.value = `${val.substring(0, 5)}-${val.substring(5, 12)}-${val.substring(12, 13)}`;
      } else if (val.length > 5) {
        e.target.value = `${val.substring(0, 5)}-${val.substring(5)}`;
      } else {
        e.target.value = val;
      }
    });
  }

  // Form Validation on Submit
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    let isValid = true;
    let firstInvalidField = null;

    // 1. Full Name
    const fullName = document.getElementById('fullName');
    if (!fullName.value.trim() || fullName.value.trim().length < 3) {
      setFieldError(fullName, 'Please enter your complete full name (minimum 3 letters)');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = fullName;
    } else {
      clearFieldError(fullName);
    }

    // 2. Father's Name
    const fatherName = document.getElementById('fatherName');
    if (!fatherName.value.trim() || fatherName.value.trim().length < 3) {
      setFieldError(fatherName, "Please enter your father's full name");
      isValid = false;
      if (!firstInvalidField) firstInvalidField = fatherName;
    } else {
      clearFieldError(fatherName);
    }

    // 3. Date of Birth
    const dob = document.getElementById('dateOfBirth');
    if (!dob.value) {
      setFieldError(dob, 'Please select your date of birth');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = dob;
    } else {
      const birthYear = new Date(dob.value).getFullYear();
      const currentYear = new Date().getFullYear();
      const age = currentYear - birthYear;
      if (age < 14 || age > 35) {
        setFieldError(dob, 'Candidate age must be between 14 and 35 years');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = dob;
      } else {
        clearFieldError(dob);
      }
    }

    // 4. Gender
    const gender = document.getElementById('gender');
    if (!gender.value) {
      setFieldError(gender, 'Please select your gender');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = gender;
    } else {
      clearFieldError(gender);
    }

    // 5. CNIC / B-Form Number
    if (cnicInput) {
      const cnicDigits = cnicInput.value.replace(/\D/g, '');
      if (cnicDigits.length !== 13) {
        setFieldError(cnicInput, 'Please enter a valid 13-digit CNIC or B-Form number (e.g. 12345-1234567-1)');
        isValid = false;
        if (!firstInvalidField) firstInvalidField = cnicInput;
      } else {
        clearFieldError(cnicInput);
      }
    }

    // 6. Phone Number
    const phone = document.getElementById('phoneNumber');
    const phoneRegex = /^(\+92|0)[0-9]{9,11}$/;
    const cleanPhone = phone.value.replace(/[\s-]/g, '');
    if (!cleanPhone || !phoneRegex.test(cleanPhone)) {
      setFieldError(phone, 'Please enter a valid phone number (e.g. 0300-1234567 or +92 300 1234567)');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = phone;
    } else {
      clearFieldError(phone);
    }

    // 7. Email Address
    const email = document.getElementById('emailAddress');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
      setFieldError(email, 'Please provide a valid email address');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = email;
    } else {
      clearFieldError(email);
    }

    // 8. Residential Address
    const address = document.getElementById('residentialAddress');
    if (!address.value.trim() || address.value.trim().length < 8) {
      setFieldError(address, 'Please enter your complete residential address');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = address;
    } else {
      clearFieldError(address);
    }

    // 9. City
    const city = document.getElementById('city');
    if (!city.value.trim()) {
      setFieldError(city, 'Please enter your city/district');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = city;
    } else {
      clearFieldError(city);
    }

    // 10. Academic Qualifications
    const qualification = document.getElementById('lastQualification');
    if (!qualification.value) {
      setFieldError(qualification, 'Please choose your last qualification');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = qualification;
    } else {
      clearFieldError(qualification);
    }

    // 11. Passing Year
    const passingYear = document.getElementById('passingYear');
    const pYear = parseInt(passingYear.value, 10);
    if (isNaN(pYear) || pYear < 2010 || pYear > 2026) {
      setFieldError(passingYear, 'Please enter a valid passing year (2010-2026)');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = passingYear;
    } else {
      clearFieldError(passingYear);
    }

    // 12. Marks & Percentage
    const totalM = parseFloat(totalMarksInput.value);
    const obtainedM = parseFloat(obtainedMarksInput.value);
    if (isNaN(totalM) || totalM <= 0) {
      setFieldError(totalMarksInput, 'Please enter valid total marks');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = totalMarksInput;
    } else {
      clearFieldError(totalMarksInput);
    }

    if (isNaN(obtainedM) || obtainedM < 0 || obtainedM > totalM) {
      setFieldError(obtainedMarksInput, 'Please enter valid obtained marks');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = obtainedMarksInput;
    } else {
      clearFieldError(obtainedMarksInput);
    }

    // 13. Previous School
    const prevSchool = document.getElementById('previousSchool');
    if (!prevSchool.value.trim()) {
      setFieldError(prevSchool, 'Please enter your previous school or college');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = prevSchool;
    } else {
      clearFieldError(prevSchool);
    }

    // 14. Program Choice
    if (!programSelect.value) {
      setFieldError(programSelect, 'Please select your preferred technical program');
      isValid = false;
      if (!firstInvalidField) firstInvalidField = programSelect;
    } else {
      clearFieldError(programSelect);
    }

    // 15. Declaration Checkbox
    const declaration = document.getElementById('declarationCheck');
    if (!declaration.checked) {
      const declError = document.getElementById('declarationError');
      if (declError) declError.style.display = 'block';
      isValid = false;
      if (!firstInvalidField) firstInvalidField = declaration;
    } else {
      const declError = document.getElementById('declarationError');
      if (declError) declError.style.display = 'none';
    }

    if (!isValid) {
      if (firstInvalidField) {
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        firstInvalidField.focus();
      }
      return;
    }

    // On Successful Validation: Render Confirmation Modal
    const refNumber = 'GPI-2026-' + Math.floor(1000 + Math.random() * 9000);
    const modal = document.getElementById('admissionSuccessModal');
    if (modal) {
      document.getElementById('receiptRefNumber').textContent = refNumber;
      document.getElementById('receiptApplicantName').textContent = fullName.value.trim();
      document.getElementById('receiptProgram').textContent = programSelect.options[programSelect.selectedIndex].text;
      document.getElementById('receiptDate').textContent = new Date().toLocaleDateString('en-PK', {
        year: 'numeric', month: 'short', day: 'numeric'
      });
      document.getElementById('receiptPhone').textContent = phone.value.trim();
      document.getElementById('receiptPercentage').textContent = percentageInput ? percentageInput.value : '';

      modal.classList.add('is-active');
      document.body.style.overflow = 'hidden';

      // Setup close handler
      const closeButtons = modal.querySelectorAll('.js-close-modal');
      closeButtons.forEach(btn => {
        btn.onclick = () => {
          modal.classList.remove('is-active');
          document.body.style.overflow = '';
          form.reset();
          if (percentageInput) percentageInput.value = '';
          // Reset file previews
          fileInputs.forEach(i => {
            const p = i.closest('.file-upload-box').querySelector('.file-upload-name');
            if (p) p.textContent = '';
          });
          window.scrollTo({ top: 0, behavior: 'smooth' });
        };
      });
    }
  });

  function setFieldError(field, message) {
    field.classList.add('is-invalid');
    field.classList.remove('is-valid');
    let errorEl = field.parentElement.querySelector('.form-error-msg');
    if (errorEl) {
      errorEl.textContent = message;
      errorEl.style.display = 'block';
    }
  }

  function clearFieldError(field) {
    field.classList.remove('is-invalid');
    field.classList.add('is-valid');
    let errorEl = field.parentElement.querySelector('.form-error-msg');
    if (errorEl) {
      errorEl.style.display = 'none';
    }
  }
}

/* --------------------------------------------------------------------------
   8. Contact Form Engine
   -------------------------------------------------------------------------- */
function initContactForm() {
  const form = document.getElementById('contactForm');
  if (!form) return;

  form.addEventListener('submit', (e) => {
    e.preventDefault();
    let isValid = true;

    const name = document.getElementById('contactName');
    const email = document.getElementById('contactEmail');
    const subject = document.getElementById('contactSubject');
    const message = document.getElementById('contactMessage');

    if (!name.value.trim() || name.value.trim().length < 3) {
      name.classList.add('is-invalid');
      isValid = false;
    } else {
      name.classList.remove('is-invalid');
      name.classList.add('is-valid');
    }

    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!email.value.trim() || !emailRegex.test(email.value.trim())) {
      email.classList.add('is-invalid');
      isValid = false;
    } else {
      email.classList.remove('is-invalid');
      email.classList.add('is-valid');
    }

    if (!subject.value.trim()) {
      subject.classList.add('is-invalid');
      isValid = false;
    } else {
      subject.classList.remove('is-invalid');
      subject.classList.add('is-valid');
    }

    if (!message.value.trim() || message.value.trim().length < 10) {
      message.classList.add('is-invalid');
      isValid = false;
    } else {
      message.classList.remove('is-invalid');
      message.classList.add('is-valid');
    }

    if (!isValid) return;

    // Show interactive toast
    const refCode = 'MSG-' + Math.floor(1000 + Math.random() * 9000);
    showToast(`✓ Message sent successfully! Ticket Ref: ${refCode}. We will get back to you shortly.`);
    form.reset();
    form.querySelectorAll('.is-valid').forEach(el => el.classList.remove('is-valid'));
  });
}

function showToast(message) {
  let container = document.querySelector('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }

  const toast = document.createElement('div');
  toast.className = 'toast toast-success is-active';
  toast.innerHTML = `<span>${message}</span>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.classList.remove('is-active');
    setTimeout(() => toast.remove(), 400);
  }, 4500);
}

/* --------------------------------------------------------------------------
   9. Gallery Category Filtering & Lightbox Viewer
   -------------------------------------------------------------------------- */
function initGallery() {
  const filterBtns = document.querySelectorAll('.filter-btn');
  const galleryItems = document.querySelectorAll('.gallery-item');
  const lightbox = document.getElementById('galleryLightbox');

  if (!galleryItems.length) return;

  // Filter Buttons
  filterBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      filterBtns.forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      const filterValue = btn.getAttribute('data-filter');

      galleryItems.forEach(item => {
        const itemCategory = item.getAttribute('data-category');
        if (filterValue === 'all' || itemCategory === filterValue) {
          item.style.display = 'block';
          item.classList.add('reveal-on-scroll', 'is-revealed');
        } else {
          item.style.display = 'none';
        }
      });
    });
  });

  // Lightbox Implementation
  if (!lightbox) return;

  let currentVisibleItems = [];
  let currentIndex = 0;

  const lightboxImg = lightbox.querySelector('.js-lightbox-img');
  const lightboxTitle = lightbox.querySelector('.js-lightbox-title');
  const lightboxDesc = lightbox.querySelector('.js-lightbox-desc');
  const lightboxCounter = lightbox.querySelector('.js-lightbox-counter');
  const closeBtn = lightbox.querySelector('.lightbox-btn-close');
  const prevBtn = lightbox.querySelector('.lightbox-btn-prev');
  const nextBtn = lightbox.querySelector('.lightbox-btn-next');

  function updateVisibleItems() {
    currentVisibleItems = Array.from(galleryItems).filter(item => item.style.display !== 'none');
  }

  function displayLightboxItem(index) {
    if (index < 0) index = currentVisibleItems.length - 1;
    if (index >= currentVisibleItems.length) index = 0;
    currentIndex = index;

    const item = currentVisibleItems[currentIndex];
    const img = item.querySelector('img');
    const title = item.querySelector('.gallery-item-title') ? item.querySelector('.gallery-item-title').textContent : '';
    const desc = item.getAttribute('data-desc') || item.querySelector('.gallery-item-category').textContent;

    if (lightboxImg) lightboxImg.src = img.src;
    if (lightboxTitle) lightboxTitle.textContent = title;
    if (lightboxDesc) lightboxDesc.textContent = desc;
    if (lightboxCounter) {
      lightboxCounter.textContent = `Photo ${currentIndex + 1} of ${currentVisibleItems.length}`;
    }
  }

  galleryItems.forEach(item => {
    item.addEventListener('click', () => {
      updateVisibleItems();
      const itemIndex = currentVisibleItems.indexOf(item);
      if (itemIndex !== -1) {
        displayLightboxItem(itemIndex);
        lightbox.classList.add('is-active');
        document.body.style.overflow = 'hidden';
      }
    });
  });

  function closeLightbox() {
    lightbox.classList.remove('is-active');
    document.body.style.overflow = '';
  }

  if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
  lightbox.addEventListener('click', (e) => {
    if (e.target === lightbox) closeLightbox();
  });

  if (prevBtn) {
    prevBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      displayLightboxItem(currentIndex - 1);
    });
  }

  if (nextBtn) {
    nextBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      displayLightboxItem(currentIndex + 1);
    });
  }

  // Keyboard navigation
  document.addEventListener('keydown', (e) => {
    if (!lightbox.classList.contains('is-active')) return;
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') displayLightboxItem(currentIndex - 1);
    if (e.key === 'ArrowRight') displayLightboxItem(currentIndex + 1);
  });

  // Mobile Touch Swipe Navigation
  let touchStartX = 0;
  let touchEndX = 0;

  lightbox.addEventListener('touchstart', (e) => {
    touchStartX = e.changedTouches[0].screenX;
  }, { passive: true });

  lightbox.addEventListener('touchend', (e) => {
    touchEndX = e.changedTouches[0].screenX;
    handleSwipe();
  }, { passive: true });

  function handleSwipe() {
    const swipeThreshold = 40;
    if (touchEndX < touchStartX - swipeThreshold) {
      // Swiped Left -> Next
      displayLightboxItem(currentIndex + 1);
    }
    if (touchEndX > touchStartX + swipeThreshold) {
      // Swiped Right -> Previous
      displayLightboxItem(currentIndex - 1);
    }
  }
}

/* --------------------------------------------------------------------------
   10. Announcements Preview Modal
   -------------------------------------------------------------------------- */
function initAnnouncementsModal() {
  const readMoreBtns = document.querySelectorAll('.announcement-card .announcement-link');
  const modal = document.getElementById('announcementModal');
  if (!modal || !readMoreBtns.length) return;

  const modalTitle = modal.querySelector('.js-announce-title');
  const modalDate = modal.querySelector('.js-announce-date');
  const modalBody = modal.querySelector('.js-announce-body');
  const closeBtn = modal.querySelector('.js-close-modal');

  readMoreBtns.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const card = btn.closest('.announcement-card');
      const title = card.querySelector('.announcement-title').textContent;
      const date = card.querySelector('.announcement-date').textContent;
      const desc = card.querySelector('.announcement-desc').textContent;

      if (modalTitle) modalTitle.textContent = title;
      if (modalDate) modalDate.textContent = 'Published: ' + date;
      if (modalBody) {
        modalBody.innerHTML = `
          <p class="mb-3">${desc}</p>
          <p class="text-slate-600">Official Directorate Notification: All interested candidates, enrolled students, and guardians are advised to review the above instructions carefully. For official verification and documentary compliance, kindly report to the Academic Coordination Office, Block-A during working hours (8:00 AM to 3:00 PM).</p>
        `;
      }

      modal.classList.add('is-active');
      document.body.style.overflow = 'hidden';
    });
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      modal.classList.remove('is-active');
      document.body.style.overflow = '';
    });
  }

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      modal.classList.remove('is-active');
      document.body.style.overflow = '';
    }
  });
}
