// ============================
// Stock Keeper Login Initialization
// ============================
document.addEventListener("DOMContentLoaded", () => {

  // ============================
  // INIT
  // ============================
  const AppCoreInstance = window.AppCoreInstance;
  const AuthApp = window.AuthApp;
  const Validator = AppCoreInstance;

  // ============================
  // SELECTORS
  // ============================
  const signupForm = document.getElementById("signupForm");
  const loginForm = document.getElementById("loginForm");
  const forgotPwdForm = document.getElementById("forgotPwdForm");
  const forgotQandAForm = document.getElementById("forgotQandAForm");
  const resetPwdForm = document.getElementById("resetPwdForm");
  const questionSelect = document.getElementById("cQuestion");
  const answerWrapper = document.getElementById("ans");

  const loginPasswordRule = {
    required: true,
    regex: /^.{1,200}$/,
    msg: "Enter your password (maximum 200 characters)."
  };
  const signupAnswerRule = {
    required: true,
    regex: /^.{2,72}$/,
    msg: "Recovery answer must be 2–72 characters."
  };

  // ============================
  // TABS
  // ============================
  const bindClick = (id, handler) => {
    const el = document.getElementById(id);
    if (el) el.addEventListener("click", handler);
  };

  bindClick("signupLink", (e) => { e.preventDefault(); AppCoreInstance.switchTab("signupTab"); });
  bindClick("loginLink", (e) => { e.preventDefault(); AppCoreInstance.switchTab("loginTab"); });
  bindClick("forgotPwdLink", (e) => { e.preventDefault(); AppCoreInstance.switchTab("forgotPwdTab"); });
  bindClick("backToLogin", (e) => { e.preventDefault(); AppCoreInstance.switchTab("loginTab"); });

  // ============================
  // SIGNUP
  // ============================
  if (signupForm) {
    signupForm.addEventListener("submit", e => {
      e.preventDefault();
      const rulesMap = {
        cEmail: Validator.rules.email,
        cPass: Validator.rules.password,
        cName: Validator.rules.name,
        cAnswer: signupAnswerRule
      };
      const ok = Validator.validateForm(signupForm, rulesMap);
      const questionValid = Boolean(questionSelect && questionSelect.value);
      const agreeEl = document.getElementById("agree");
      const agreementValid = agreeEl ? agreeEl.checked : false;

      const qCheck = document.getElementById("cQuestionCheck");
      if (qCheck) qCheck.style.display = questionValid ? "none" : "block";

      const agreeCheck = document.getElementById("agreecheck");
      if (agreeCheck) agreeCheck.style.display = agreementValid ? "none" : "block";

      const cPassVal = document.getElementById("cPass")?.value || "";
      const conCPassVal = document.getElementById("conCPass")?.value || "";
      const passMatch = cPassVal === conCPassVal;

      if (!passMatch) {
        Validator.setTextError("#conCPasscheck", "Passwords do not match");
        return;
      } else {
        Validator.setTextError("#conCPasscheck", "");
      }

      if (!ok || !questionValid || !agreementValid) {
        AppCoreInstance.showAlert("Please correct the errors.", "error");
        return;
      }

      AuthApp.signup({
        email: document.getElementById("cEmail").value.trim(),
        password: cPassVal,
        name: document.getElementById("cName").value.trim(),
        question: questionSelect.value,
        answer: document.getElementById("cAnswer").value.trim(),
        agree: agreementValid ? "1" : "0"
      });
    });
  }

  // ============================
  // LOGIN
  // ============================
  if (loginForm) {
    loginForm.addEventListener("submit", e => {
      e.preventDefault();
      const rulesMap = {
        email: Validator.rules.email,
        pass: loginPasswordRule
      };
      const ok = Validator.validateForm(loginForm, rulesMap);
      if (!ok) {
        AppCoreInstance.showAlert("Invalid login credentials.", "error");
        return;
      }
      AuthApp.login({
        email: document.getElementById("email").value.trim(),
        password: document.getElementById("pass").value
      });
    });
  }

  // ============================
  // FORGOT PASSWORD
  // ============================
  if (forgotPwdForm) {
    forgotPwdForm.addEventListener("submit", e => {
      e.preventDefault();
      const rulesMap = { fEmail: Validator.rules.email };
      if (!Validator.validateForm(forgotPwdForm, rulesMap)) return;
      AuthApp.requestPasswordReset(document.getElementById("fEmail").value.trim());
    });
  }

  // ============================
  // FORGOT Q & A
  // ============================
  if (forgotQandAForm) {
    forgotQandAForm.addEventListener("submit", e => {
      e.preventDefault();
      const rulesMap = { fAnswer: Validator.rules.answer };
      if (!Validator.validateForm(forgotQandAForm, rulesMap)) return;
      AuthApp.forgotQandA(document.getElementById("fAnswer").value.trim());
    });
  }

  // ============================
  // RESET PASSWORD
  // ============================
  if (resetPwdForm) {
    resetPwdForm.addEventListener("submit", e => {
      e.preventDefault();
      const rulesMap = { rPass: Validator.rules.password };
      if (!Validator.validateForm(resetPwdForm, rulesMap)) return;

      const rPassVal = document.getElementById("rPass").value;
      const rConPassVal = document.getElementById("rConPass").value;

      if (rPassVal !== rConPassVal) {
        Validator.setTextError("#rConPassCheck", "Passwords do not match");
        return;
      } else {
        Validator.setTextError("#rConPassCheck", "");
      }
      AuthApp.resetPassword(rPassVal);
    });
  }

  // ============================
  // SECURITY QUESTION TOGGLE
  // ============================
  if (questionSelect) {
    const handleQuestionChange = function () {
      const cAnswerInput = document.getElementById("cAnswer");
      const qCheck = document.getElementById("cQuestionCheck");

      if (!this.value) {
        if (answerWrapper) answerWrapper.style.display = "none";
        if (cAnswerInput) {
          cAnswerInput.value = "";
          cAnswerInput.required = false;
        }
      } else {
        if (answerWrapper) answerWrapper.style.display = "block";
        if (cAnswerInput) cAnswerInput.required = true;
      }
      if (this.value && qCheck) qCheck.style.display = "none";
    };

    questionSelect.addEventListener("change", handleQuestionChange);
    // Trigger initial check state
    handleQuestionChange.call(questionSelect);
  }

  // ============================
  // LIVE VALIDATION HELPER
  // ============================
  const live = (sel, rule, err) => {
    const el = document.querySelector(sel);
    if (el) {
      ["input", "blur"].forEach(evt => {
        el.addEventListener(evt, () => Validator.validateField(sel, rule, err));
      });
    }
  };

  live("#cEmail", Validator.rules.email, "#cEmailValid");
  live("#cPass", Validator.rules.password, "#cpasscheck");
  live("#cName", Validator.rules.name, "#cNameCheck");
  live("#cAnswer", signupAnswerRule, "#cAnswerCheck");
  live("#email", Validator.rules.email, "#emailvalid");
  live("#pass", loginPasswordRule, "#passCheck");
  live("#fEmail", Validator.rules.email, "#fEmailCheck");
  live("#fAnswer", Validator.rules.answer, "#fAnswerCheck");
  live("#rPass", Validator.rules.password, "#rPassCheck");

  const agreeCheckbox = document.getElementById("agree");
  if (agreeCheckbox) {
    agreeCheckbox.addEventListener("change", function () {
      const agreeCheckEl = document.getElementById("agreecheck");
      if (agreeCheckEl) agreeCheckEl.style.display = this.checked ? "none" : "block";
    });
  }

  // ============================
  // CONFIRM PASSWORD LIVE
  // ============================
  document.querySelectorAll("#conCPass, #rConPass").forEach(input => {
    input.addEventListener("input", function () {
      const form = this.closest("form");
      const firstPass = form ? form.querySelector("input[type=password]") : null;
      const firstPassword = firstPass ? firstPass.value : "";
      const confirmation = this.value;
      const match = confirmation.length > 0 && confirmation === firstPassword;

      this.classList.toggle("is-valid", match);
      this.classList.toggle("is-invalid", confirmation.length > 0 && !match);
    });
  });

  // ============================
  // PASSWORD STRENGTH
  // ============================
  document.querySelectorAll("#cPass, #rPass").forEach(input => {
    input.addEventListener("input", function () {
      const pass = this.value;
      const score =
        Number(pass.length >= 8) +
        Number(pass.length >= 12) +
        Number(/[a-z]/.test(pass) && /[A-Z]/.test(pass)) +
        Number(/[0-9]/.test(pass)) +
        Number(/[^A-Za-z0-9]/.test(pass));
      
      const labels = ["Enter a password", "Very weak", "Weak", "Fair", "Strong", "Very strong"];
      const strengthEl = document.getElementById("passwordStrength");
      if (strengthEl) {
        strengthEl.textContent = labels[Math.min(score, labels.length - 1)];
      }
    });
  });

  // ============================
  // CAPS LOCK WARNING
  // ============================
  document.querySelectorAll("input[type=password]").forEach(input => {
    input.addEventListener("keyup", e => {
      const capsWarning = document.getElementById("capsWarning");
      if (capsWarning) {
        const isCapsLock = e.getModifierState && e.getModifierState("CapsLock");
        capsWarning.style.display = isCapsLock ? "block" : "none";
      }
    });
  });

});