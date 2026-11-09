document.addEventListener("DOMContentLoaded", () => {
  const card = document.querySelector(".auth-card");
  const layout = document.querySelector(".auth-layout");
  const accountType = document.getElementById("authAccountType");
  if (!card || !layout || !accountType) return;

  const params = new URLSearchParams(window.location.search);
  const choices = document.createElement("section");
  choices.className = "auth-choice-grid";
  choices.setAttribute("aria-label", "Choose your Bubba Hub account");
  choices.innerHTML = `
    <button type="button" class="auth-choice auth-choice-family" data-open-auth="family">
      <span class="auth-choice-icon" aria-hidden="true">👨‍👩‍👧‍👦</span>
      <h2>Family account</h2>
      <p>Find local activities, save favourites and organise your family week.</p>
      <span class="auth-choice-cta">Sign in or create a family account <span aria-hidden="true">→</span></span>
    </button>
    <button type="button" class="auth-choice auth-choice-leader" data-open-auth="leader">
      <span class="auth-choice-icon" aria-hidden="true">🎨</span>
      <h2>Class leader account</h2>
      <p>Manage your activity listings, classes and organisation details.</p>
      <span class="auth-choice-cta">Sign in or register as a class leader <span aria-hidden="true">→</span></span>
    </button>`;

  layout.parentNode.insertBefore(choices, layout);

  const teamLink = document.createElement("p");
  teamLink.className = "auth-choice-foot";
  teamLink.innerHTML = 'Part of the Bubba Hub team? <button type="button" data-open-auth="volunteer">Team / volunteer sign in</button>';
  choices.insertAdjacentElement("afterend", teamLink);

  const backdrop = document.createElement("div");
  backdrop.className = "auth-modal-backdrop";
  backdrop.id = "authModalBackdrop";
  backdrop.setAttribute("aria-hidden", "true");
  backdrop.innerHTML = `
    <section class="auth-modal" role="dialog" aria-modal="true" aria-labelledby="authModalTitle" tabindex="-1">
      <button type="button" class="auth-modal-close" aria-label="Close sign in">×</button>
      <div class="auth-modal-heading">
        <h2 id="authModalTitle">Sign in</h2>
        <p id="authModalIntro">Access your Bubba Hub account.</p>
      </div>
      <div class="auth-role-switch" role="group" aria-label="Account type">
        <button type="button" data-role="family" aria-pressed="true">Family</button>
        <button type="button" data-role="leader" aria-pressed="false">Class leader</button>
      </div>
    </section>`;
  document.body.appendChild(backdrop);

  const modal = backdrop.querySelector(".auth-modal");
  const closeButton = backdrop.querySelector(".auth-modal-close");
  const heading = backdrop.querySelector("#authModalTitle");
  const intro = backdrop.querySelector("#authModalIntro");
  const roleSwitch = backdrop.querySelector(".auth-role-switch");
  const roleButtons = [...roleSwitch.querySelectorAll("[data-role]")];
  const focusableSelector = 'a[href], button:not([disabled]):not([hidden]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  let returnFocus = null;

  modal.appendChild(card);
  document.body.classList.add("auth-modal-experience-ready");

  const setRole = (role) => {
    accountType.value = role;
    roleSwitch.hidden = role === "volunteer";
    roleButtons.forEach((button) => {
      button.setAttribute("aria-pressed", String(button.dataset.role === role));
    });

    const label = role === "leader" ? "Class leader" : role === "volunteer" ? "Team / volunteer" : "Family";
    heading.textContent = label + (role === "volunteer" ? " sign in" : " account");
    intro.textContent = role === "leader"
      ? "Sign in or create an account to manage your listings and classes."
      : role === "volunteer"
        ? "Sign in to access your approved Bubba Hub team account."
        : "Sign in or create an account to access your saved activities and family planner.";

    accountType.dispatchEvent(new Event("change", { bubbles: true }));
  };

  const openModal = (role, trigger) => {
    returnFocus = trigger || document.activeElement;
    setRole(role);
    backdrop.classList.add("is-open");
    backdrop.setAttribute("aria-hidden", "false");
    document.body.classList.add("auth-modal-open");
    const email = card.querySelector('input[name="email"]');
    (email || closeButton).focus();
  };

  const closeModal = () => {
    if (!backdrop.classList.contains("is-open")) return;
    backdrop.classList.remove("is-open");
    backdrop.setAttribute("aria-hidden", "true");
    document.body.classList.remove("auth-modal-open");
    if (returnFocus && typeof returnFocus.focus === "function") returnFocus.focus();
  };

  choices.querySelectorAll("[data-open-auth]").forEach((button) => {
    button.addEventListener("click", () => openModal(button.dataset.openAuth, button));
  });
  teamLink.querySelector("button").addEventListener("click", (event) => {
    openModal("volunteer", event.currentTarget);
  });
  roleButtons.forEach((button) => {
    button.addEventListener("click", () => setRole(button.dataset.role));
  });

  closeButton.addEventListener("click", closeModal);
  backdrop.addEventListener("click", (event) => {
    if (event.target === backdrop) closeModal();
  });

  modal.addEventListener("keydown", (event) => {
    if (event.key !== "Tab") return;
    const focusable = [...modal.querySelectorAll(focusableSelector)].filter((element) =>
      !element.hidden && element.getAttribute("aria-hidden") !== "true" && element.getClientRects().length
    );
    if (!focusable.length) {
      event.preventDefault();
      modal.focus();
      return;
    }
    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement === modal)) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key === "Escape" && backdrop.classList.contains("is-open")) closeModal();
  });

  document.querySelectorAll("[data-auth-mode]").forEach((tab) => {
    tab.addEventListener("click", () => {
      const role = accountType.value === "leader" ? "Class leader" : accountType.value === "volunteer" ? "Team / volunteer" : "Family";
      heading.textContent = role + (tab.dataset.authMode === "register" ? " registration" : " sign in");
    });
  });

  const requestedContext = params.get("context");
  const requestedMode = params.get("mode");
  const shouldOpen = params.has("context") || params.has("mode") || params.has("next") || params.has("event");
  if (shouldOpen) {
    const requestedNext = params.get("next") || "";
    const role = requestedContext === "leader" || requestedNext.startsWith("/leader/") || requestedNext.startsWith("/claim-invitation.html") ? "leader" : requestedContext === "volunteer" || requestedNext.startsWith("/team-hub/") ? "volunteer" : "family";
    openModal(role, null);
  }

  if (requestedMode === "register" || params.get("event") === "1") {
    const registerTab = document.querySelector('[data-auth-mode="register"]');
    if (registerTab) registerTab.click();
  }
});
