// Shared browser utilities and API client.
class AppCore {
  constructor(csrfToken = '') {
    this.csrfToken = csrfToken;
    this.numberFormatter = new Intl.NumberFormat('en-NG', { maximumFractionDigits: 3 });
    this.currencyFormatter = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', minimumFractionDigits: 2, maximumFractionDigits: 2 });
    this.rules = {
      email: { required: true, regex: /^[^\s@]+@[^\s@]+\.[^\s@]{2,190}$/, msg: 'Enter a valid email address (maximum 190 characters).' },
      password: { required: true, regex: /^.{8,72}$/, msg: 'Password must be 8–72 characters.' },
      phone: { required: true, regex: /^\+?[0-9\s-]{7,15}$/, msg: 'Invalid phone number.' },
      amount: { required: true, regex: /^\d+(\.\d{1,2})?$/, validate: value => value >= 100 && value <= 5000000, msg: 'Invalid amount.' },
      name: { required: true, regex: /^.{2,120}$/, msg: 'Name must be 2–120 characters.' },
      address: { required: true, regex: /^.{5,100}$/, msg: 'Address must be 5–100 characters.' },
      answer: { required: true, regex: /^.{2,200}$/, msg: 'Answer must be 2–200 characters.' },
      description: { required: false, regex: /^.{0,200}$/, msg: 'Description must be 200 characters or fewer.' },
      category: { required: true, regex: /^[a-zA-Z0-9 .'-]{2,30}$/, msg: 'Category must be 2–30 letters or numbers.' },
      product: { required: true, regex: /^[a-zA-Z0-9 .'-]{2,50}$/, msg: 'Product name must be 2–50 letters or numbers.' }
    };
  }

  async requestApi(url, action, data = {}, options = {}) {
    const { method = 'POST', signal, headers = {}, timeout = 30000 } = options;
    const requestHeaders = { ...headers, 'X-CSRF-Token': this.csrfToken };
    let body;
    if (data instanceof FormData) {
      body = data;
      body.set('action', action);
      body.set('csrf_token', this.csrfToken);
    } else {
      const payload = new URLSearchParams();
      Object.entries({ ...data, action, csrf_token: this.csrfToken }).forEach(([key, value]) => {
        if (value !== undefined && value !== null) payload.set(key, String(value));
      });
      body = payload;
      requestHeaders['Content-Type'] = requestHeaders['Content-Type'] || 'application/x-www-form-urlencoded; charset=UTF-8';
    }

    const controller = new AbortController();
    const abortFromCaller = () => controller.abort();
    if (signal) {
      if (signal.aborted) controller.abort();
      else signal.addEventListener('abort', abortFromCaller, { once: true });
    }
    const timeoutId = timeout > 0 ? window.setTimeout(() => controller.abort(), timeout) : null;
    try {
      const response = await fetch(url, { method, headers: requestHeaders, body, credentials: 'same-origin', signal: controller.signal });
      let payload;
      try { payload = await response.json(); }
      catch { throw new Error('The server returned an invalid response.'); }

      if (response.status === 401) {
        if (action !== 'login') window.location.href = 'login.php';
        const error = new Error(payload?.message || 'Your session has expired. Please log in again.');
        error.status = 401;
        error.redirected = action !== 'login';
        error.payload = payload;
        error.responseJSON = payload;
        throw error;
      }
      if (!response.ok || !payload || payload.status !== 'success') {
        const error = new Error(payload?.message || 'The request could not be completed.');
        error.status = response.status;
        error.payload = payload;
        error.responseJSON = payload;
        throw error;
      }
      return payload;
    } catch (error) {
      if (error.name === 'AbortError' && !signal?.aborted) error.message = 'Request timed out. Please try again.';
      throw error;
    } finally {
      if (timeoutId !== null) window.clearTimeout(timeoutId);
      if (signal) signal.removeEventListener('abort', abortFromCaller);
    }
  }

  async ajaxHelper({ url = '', method = 'POST', action = '', dir = '', data = {}, onSuccess = null, onComplete = null, successMsg = 'Operation successful.', errorMsg = 'Operation failed.' } = {}) {
    if (!url || !action) {
      this.showAlert('A request URL and action are required.', 'error');
      return;
    }
    const payload = data instanceof FormData ? new FormData(data) : { ...data };
    if (dir) {
      if (payload instanceof FormData) payload.set('dir', dir);
      else payload.dir = dir;
    }
    let result = null;
    try {
      result = await this.requestApi(url, action, payload, { method });
      this.showAlert(result.message || successMsg, 'success');
      if (typeof onSuccess === 'function') onSuccess(result);
    } catch (error) {
      if (!error.redirected) this.showAlert(error.message || errorMsg, 'error');
      if (typeof onComplete === 'function') onComplete(error.payload || null);
      return;
    }
    if (typeof onComplete === 'function') onComplete(result);
  }

  escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value == null ? '' : String(value);
    return element.innerHTML;
  }

  toNumber(value, fallback = 0) {
    const number = Number(value);
    return Number.isFinite(number) ? number : fallback;
  }

  formatQuantity(value) { return this.numberFormatter.format(this.toNumber(value)); }
  formatMoney(value) { return this.currencyFormatter.format(this.toNumber(value)); }
  formatNumber(value) { return this.formatQuantity(value); }
  formatCurrency(value) { return this.formatMoney(value); }

  formatDate(value, fallback = '—', options = {}) {
    if (value === null || value === undefined || value === '') return fallback;
    let dateValue = value;
    if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/.test(value.trim())) dateValue = value.trim().replace(' ', 'T') + 'Z';
    const date = new Date(dateValue);
    if (Number.isNaN(date.getTime())) return fallback;
    return new Intl.DateTimeFormat('en-NG', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true, timeZone: 'Africa/Lagos', ...options }).format(date);
  }

  formatDateSafe(value, fallback = '—') { return this.formatDate(value, fallback); }

  setText(target, value) {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (element) element.textContent = value == null ? '' : String(value);
  }

  renderTableMessage(target, colspan, message) {
    const element = typeof target === 'string' ? document.querySelector(target) : target;
    if (!element) return;
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = colspan;
    cell.className = 'text-center text-muted';
    cell.textContent = message;
    row.append(cell);
    element.replaceChildren(row);
  }

  setFieldError(fieldSelector, message = '', errorSelector = null) {
    const field = typeof fieldSelector === 'string' ? document.querySelector(fieldSelector) : fieldSelector;
    if (!field) return;
    const target = errorSelector || (field.id ? '#' + CSS.escape(field.id) + '_err' : null);
    const errorElement = target ? document.querySelector(target) : null;
    field.classList.toggle('is-invalid', Boolean(message));
    if (errorElement) {
      errorElement.textContent = message;
      errorElement.style.display = message ? 'block' : 'none';
    } else if (message) field.setAttribute('title', message);
    if (message) field.setAttribute('aria-invalid', 'true');
    else { field.removeAttribute('aria-invalid'); field.removeAttribute('title'); }
  }

  setTextError(selector, message = '') {
    const element = document.querySelector(selector);
    if (!element) return;
    element.textContent = message;
    element.style.display = message ? 'block' : 'none';
  }

  validateField(fieldInput, rule, errorSelector = null) {
    const field = typeof fieldInput === 'string' ? document.querySelector(fieldInput) : fieldInput;
    const value = String(field?.value ?? '').trim();
    const valid = Boolean(field) && !(rule.required && !value) && !(rule.regex && !rule.regex.test(value)) && !(rule.validate && !rule.validate(Number.parseFloat(value)));
    this.setFieldError(field, valid ? '' : rule.msg, errorSelector);
    return valid;
  }

  validateForm(formInput, rulesMap) {
    const form = typeof formInput === 'string' ? document.querySelector(formInput) : formInput;
    if (!form) return false;
    return Object.entries(rulesMap).map(([name, rule]) => this.validateField(form.elements.namedItem(name), rule, '#' + CSS.escape(name) + '_err')).every(Boolean);
  }

  initPasswordToggle(root = document) {
    root.querySelectorAll('input[type="password"]').forEach(input => {
      if (input.parentElement?.classList.contains('password-wrapper')) return;
      const wrapper = document.createElement('div');
      wrapper.className = 'password-wrapper';
      wrapper.style.position = 'relative';
      input.parentElement.insertBefore(wrapper, input);
      wrapper.append(input);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'toggle-pass btn btn-link p-0';
      button.setAttribute('aria-label', 'Show password');
      button.textContent = '👁️';
      button.style.cssText = 'position:absolute;right:10px;top:50%;transform:translateY(-50%);';
      wrapper.append(button);
      button.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? '🙈' : '👁️';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
      });
    });
  }

  showAlert(message, type = 'success') {
    document.querySelectorAll('.showAlert').forEach(element => element.remove());
    const box = document.createElement('div');
    box.className = 'showAlert text-white ' + (type === 'success' ? 'bg-success' : 'bg-danger');
    box.setAttribute('role', 'alert');
    box.style.cssText = 'position:fixed;top:10px;right:10px;z-index:9999;padding:12px 16px;border-radius:6px;box-shadow:0 4px 6px rgba(0,0,0,.1);';
    const text = document.createElement('span');
    text.textContent = message;
    const close = document.createElement('button');
    close.type = 'button'; close.className = 'btn-close btn-close-white ms-3'; close.setAttribute('aria-label', 'Close');
    close.addEventListener('click', () => box.remove());
    box.append(text, close);
    document.body.append(box);
    window.setTimeout(() => { if (box.isConnected) box.remove(); }, 5000);
  }

  switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(element => { element.hidden = true; element.style.display = 'none'; });
    const target = document.getElementById(tabId);
    if (target) { target.hidden = false; target.style.display = 'block'; }
  }

  static safeHideModal(selector) {
    const element = document.querySelector(selector);
    if (!element || !window.bootstrap?.Modal) return;
    if (element.contains(document.activeElement)) document.activeElement.blur();
    (bootstrap.Modal.getInstance(element) || new bootstrap.Modal(element)).hide();
  }
}

class Auth {
  constructor(appCore) { this.app = appCore; }
  login(data) { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'login', data, onSuccess: () => { window.location.href = 'index.php'; } }); }
  signup(data) { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'signup', data, onSuccess: () => this.app.switchTab('loginTab') }); }
  logout() { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'logout', onSuccess: () => { window.location.href = 'login.php'; } }); }
  loadCompanyLogo() { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'cLogo', onSuccess: response => { const image = document.getElementById('cLogo'); if (image && response.data?.logo) image.src = 'Images/companyDP/' + encodeURIComponent(response.data.logo); } }); }
  requestPasswordReset(email) { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'requestPasswordReset', data: { fEmail: email }, onSuccess: response => { const question = document.getElementById('fQuestion'); if (question) question.value = response.data?.question || ''; this.app.switchTab('forgotQandATab'); } }); }
  forgotQandA(answer) { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'forgotQandA', data: { answer }, onSuccess: () => this.app.switchTab('resetPwdTab') }); }
  resetPassword(password) { this.app.ajaxHelper({ url: 'apiAuthentications.php', action: 'resetPassword', data: { pwd: password }, onSuccess: () => this.app.switchTab('loginTab') }); }
}

const csrfMeta = document.querySelector('meta[name="csrf-token"]');
window.AppCoreInstance = new AppCore(csrfMeta?.content || '');
window.AuthApp = new Auth(window.AppCoreInstance);

window.addEventListener('DOMContentLoaded', () => {
  window.AuthApp.loadCompanyLogo();
  const logout = document.getElementById('logout');
  logout?.addEventListener('click', event => { event.preventDefault(); if (window.confirm('End your session?')) window.AuthApp.logout(); });

  document.addEventListener('click', event => {
    const image = event.target.closest?.('.image');
    if (image) {
      const viewer = document.getElementById('imageViewer');
      const viewerImage = document.getElementById('viewerImg');
      if (viewer && viewerImage) { viewerImage.src = image.src; viewer.hidden = false; viewer.style.display = 'block'; }
    }
    if (event.target.closest?.('.close-viewer') || event.target.id === 'imageViewer') {
      const viewer = document.getElementById('imageViewer');
      if (viewer) { viewer.hidden = true; viewer.style.display = 'none'; }
    }
  });

  const applyTheme = () => {
    const saved = localStorage.getItem('theme');
    const dark = saved === 'dark' || ((!saved || saved === 'system') && window.matchMedia?.('(prefers-color-scheme: dark)').matches);
    document.body.classList.toggle('dark-mode', Boolean(dark));
    document.body.classList.toggle('compact-display', localStorage.getItem('displayDensity') === 'compact');
  };
  applyTheme();
  document.getElementById('toggleTheme')?.addEventListener('click', () => {
    const dark = !document.body.classList.contains('dark-mode');
    document.body.classList.toggle('dark-mode', dark);
    localStorage.setItem('theme', dark ? 'dark' : 'light');
  });
  window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener?.('change', () => {
    if (!localStorage.getItem('theme') || localStorage.getItem('theme') === 'system') applyTheme();
  });
  window.AppCoreInstance.initPasswordToggle();
});

document.addEventListener('hide.bs.modal', event => {
  if (event.target.contains(document.activeElement)) document.activeElement.blur();
});
