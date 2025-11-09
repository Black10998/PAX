(function () {
  console.log('PAX-ASSETS: IIFE executing - assets.js loaded');
  const cfg = window.paxSupportPro || {};
  const opts = cfg.options || {};
  const rest = cfg.rest || {};
  const links = cfg.links || {};
  const strings = cfg.strings || {};
  const user = cfg.user || {};
  const nonce = cfg.nonce;
  const isLoggedIn = !!cfg.isLoggedIn;
  console.log('PAX-ASSETS: Configuration loaded, isLoggedIn:', isLoggedIn);
  const scheduler = cfg.scheduler || {};
  const scheduleBase = (function () {
    if (rest.scheduleBase) {
      return rest.scheduleBase;
    }
    if (rest.schedule) {
      return rest.schedule.replace(/\/?$/, '/') + '';
    }
    return '';
  })();

  // v5.4.9: Updated to match actual element IDs in unified chat
  const launcher = document.getElementById('pax-unified-launcher') || document.getElementById('pax-launcher');
  const chat = document.getElementById('pax-chat');
  const chatOverlay = document.getElementById('pax-chat-overlay');
  const log = document.getElementById('pax-messages') || document.getElementById('pax-log');
  const input = document.getElementById('pax-input') || document.getElementById('pax-in');
  const sendBtn = document.getElementById('pax-send');
  const closeBtn = document.getElementById('pax-close');
  const menuBtn = document.getElementById('pax-head-more');
  const menu = document.getElementById('pax-head-menu');
  const offlineBadge = document.getElementById('pax-offline');
  const suggestionsWrap = document.getElementById('pax-suggestions');

  const aiEndpoint = rest.ai || rest.chat;
  const aiEnabled = cfg.aiEnabled !== false;
  const userLocale = cfg.locale || cfg.siteLocale || 'en';
  const historyStack = [];
  let sessionId = 'sess-' + Date.now().toString(36);
  try {
    if (window.sessionStorage) {
      const stored = window.sessionStorage.getItem('pax-ai-session');
      if (stored) {
        sessionId = stored;
      } else {
        window.sessionStorage.setItem('pax-ai-session', sessionId);
      }
    }
  } catch (err) {
    // Ignore session storage errors.
  }

  let cooldownInfo = { active: false, until: 0 };

  if (!launcher || !chat || !log) {
    console.warn('PAX-ASSETS: Chat DOM elements not found, exiting early');
    console.log('PAX-ASSETS: launcher:', !!launcher, 'chat:', !!chat, 'log:', !!log);
    return;
  }

  // Unified Chat Access Control
  const chatAccess = opts.chat_access_control || 'everyone';
  
  // Check if chat should be blocked
  function shouldBlockChat() {
    if (chatAccess === 'disabled') {
      return 'disabled';
    }
    if (chatAccess === 'logged_in' && !isLoggedIn) {
      return 'login_required';
    }
    return false;
  }
  
  const blockReason = shouldBlockChat();

  function ensureToastStack() {
    let stack = document.getElementById('pax-toast-stack');
    if (!stack) {
      stack = document.createElement('div');
      stack.id = 'pax-toast-stack';
      document.body.appendChild(stack);
    }
    return stack;
  }

  function showToast(message) {
    if (!message) {
      return;
    }
    const stack = ensureToastStack();
    const toast = document.createElement('div');
    toast.className = 'pax-toast';
    toast.textContent = message;
    stack.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add('show');
    });
    setTimeout(function () {
      toast.classList.remove('show');
      setTimeout(function () {
        toast.remove();
      }, 320);
    }, 4200);
  }

  // Enhanced REST fetch with retry logic and cache-bypass
  function restFetch(url, options, retryCount = 0) {
    if (!url) {
      return Promise.reject(new Error('Missing endpoint'));
    }
    
    // Add cache-bypass timestamp to URL
    const separator = url.includes('?') ? '&' : '?';
    const cacheBustUrl = url + separator + '_t=' + Date.now();
    
    const opts = Object.assign({ 
      credentials: 'same-origin',
      keepalive: true 
    }, options || {});
    
    opts.headers = Object.assign({
      'Cache-Control': 'no-cache, no-store, must-revalidate',
      'Pragma': 'no-cache',
      'Expires': '0'
    }, opts.headers || {});
    
    if (nonce) {
      opts.headers['X-WP-Nonce'] = nonce;
    }
    
    return fetch(cacheBustUrl, opts).catch(function(error) {
      // Retry up to 3 times on network errors
      if (retryCount < 3) {
        return new Promise(function(resolve) {
          setTimeout(function() {
            resolve(restFetch(url, options, retryCount + 1));
          }, 1000 * (retryCount + 1)); // Exponential backoff
        });
      }
      throw error;
    });
  }
  
  // Heartbeat ping to keep connection alive
  let heartbeatInterval;
  function startHeartbeat() {
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval);
    }
    heartbeatInterval = setInterval(function() {
      if (rest.chat && navigator.onLine) {
        fetch(rest.chat + '?ping=1&_t=' + Date.now(), {
          method: 'HEAD',
          credentials: 'same-origin',
          keepalive: true
        }).catch(function() {
          // Silently fail, will retry next interval
        });
      }
    }, 30000); // Every 30 seconds
  }
  
  // Start heartbeat when chat is opened
  if (chat) {
    const observer = new MutationObserver(function(mutations) {
      mutations.forEach(function(mutation) {
        if (mutation.target.classList.contains('open')) {
          startHeartbeat();
        }
      });
    });
    observer.observe(chat, { attributes: true, attributeFilter: ['class'] });
  }

  function createModalShell(id, title) {
    const wrap = document.createElement('div');
    wrap.id = id;
    wrap.className = 'pax-modal';
    wrap.setAttribute('role', 'dialog');
    wrap.setAttribute('aria-modal', 'true');
    wrap.innerHTML = '<div class="box"><button type="button" class="pax-modal-close" aria-label="' +
      (strings.close || 'Close') + '">×</button><h3>' + title + '</h3><div class="pax-modal-body"></div></div>';
    const box = wrap.querySelector('.box');
    const body = box.querySelector('.pax-modal-body');
    const titleNode = box.querySelector('h3');
    const actions = document.createElement('div');
    actions.className = 'actions';
    box.appendChild(actions);
    document.body.appendChild(wrap);

    function close() {
      if (wrap.classList.contains('closing')) {
        return;
      }
      const box = wrap.querySelector('.box');
      let cleaned = false;
      function cleanup() {
        if (cleaned) {
          return;
        }
        cleaned = true;
        wrap.classList.remove('closing');
        if (box) {
          box.removeEventListener('animationend', onAnimEnd);
          box.removeEventListener('transitionend', onAnimEnd);
        }
      }
      function onAnimEnd(event) {
        if (event.target === box) {
          cleanup();
        }
      }
      if (box) {
        box.addEventListener('animationend', onAnimEnd);
        box.addEventListener('transitionend', onAnimEnd);
      }
      wrap.classList.add('closing');
      if (typeof window.requestAnimationFrame === 'function') {
        window.requestAnimationFrame(function () {
          wrap.classList.remove('open');
        });
      } else {
        wrap.classList.remove('open');
      }
      const rootStyles = window.getComputedStyle(document.documentElement);
      const durationVar = rootStyles.getPropertyValue('--pax-animation-duration') || '';
      let duration = parseFloat(durationVar);
      if (!Number.isFinite(duration) || duration <= 0) {
        duration = 0.45;
      }
      const fallbackDelay = Math.max(160, (duration * 1000) + 120);
      window.setTimeout(cleanup, fallbackDelay);
    }

    function open() {
      wrap.classList.remove('closing');
      wrap.classList.add('open');
    }

    // Close on backdrop click
    wrap.addEventListener('click', function (event) {
      if (event.target === wrap) {
        close();
      }
    });

    // Close button handler
    const closeBtn = wrap.querySelector('.pax-modal-close');
    closeBtn.addEventListener('click', close);
    
    // Close on ESC key
    function handleEscape(event) {
      if (event.key === 'Escape' && wrap.classList.contains('open')) {
        close();
      }
    }
    
    // Add ESC listener when modal opens
    const originalOpen = open;
    open = function() {
      originalOpen();
      document.addEventListener('keydown', handleEscape);
    };
    
    // Remove ESC listener when modal closes
    const originalClose = close;
    close = function() {
      document.removeEventListener('keydown', handleEscape);
      originalClose();
    };

    return {
      element: wrap,
      body: body,
      actions: actions,
      title: titleNode,
      close: close,
      open: open,
    };
  }

  function buildModal(id, title, fields, submitLabel) {
    const shell = createModalShell(id, title);
    fields.forEach(function (field) {
      const el = document.createElement(field.tag);
      el.id = field.id;
      el.className = field.className || '';
      if (field.type) {
        el.type = field.type;
      }
      if (field.rows) {
        el.rows = field.rows;
      }
      if (field.placeholder) {
        el.placeholder = field.placeholder;
      }
      if (field.autocomplete) {
        el.autocomplete = field.autocomplete;
      }
      if (typeof field.value !== 'undefined') {
        el.value = field.value;
      }
      if (field.min) {
        el.min = field.min;
      }
      if (field.max) {
        el.max = field.max;
      }
      if (field.step) {
        el.step = field.step;
      }
      if (field.required) {
        el.required = true;
      }
      if (field.readOnly) {
        el.readOnly = true;
      }
      if (field.attrs) {
        Object.keys(field.attrs).forEach(function (attr) {
          el.setAttribute(attr, field.attrs[attr]);
        });
      }
      if (field.options && el.tagName === 'SELECT') {
        field.options.forEach(function (opt) {
          const option = document.createElement('option');
          option.value = opt.value;
          option.textContent = opt.label;
          if (opt.disabled) {
            option.disabled = true;
          }
          if (opt.selected) {
            option.selected = true;
          }
          el.appendChild(option);
        });
      }
      shell.body.appendChild(el);
    });

    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn muted';
    cancel.textContent = strings.cancel || 'Cancel';

    const submit = document.createElement('button');
    submit.type = 'button';
    submit.className = 'btn primary';
    submit.textContent = submitLabel;

    shell.actions.appendChild(cancel);
    shell.actions.appendChild(submit);

    cancel.addEventListener('click', shell.close);

    function open() {
      shell.open();
      setTimeout(function () {
        const first = shell.body.querySelector('input, textarea, select');
        if (first) {
          first.focus();
        }
      }, 60);
    }

    function getValues() {
      const values = {};
      fields.forEach(function (field) {
        const node = shell.body.querySelector('#' + field.id);
        if (node) {
          values[field.id] = (node.value || '').trim();
        }
      });
      return values;
    }

    return {
      element: shell.element,
      body: shell.body,
      submit: submit,
      open: open,
      close: shell.close,
      getValues: getValues,
      setLoading: function (text) {
        submit.disabled = true;
        submit.textContent = text;
      },
      resetSubmit: function () {
        submit.disabled = false;
        submit.textContent = submitLabel;
      },
    };
  }

  function buildInfoModal(id, title) {
    const shell = createModalShell(id, title);
    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'btn primary';
    closeBtn.textContent = strings.close || 'Close';
    shell.actions.appendChild(closeBtn);
    closeBtn.addEventListener('click', shell.close);

    return {
      element: shell.element,
      body: shell.body,
      open: shell.open,
      close: shell.close,
      setTitle: function (value) {
        if (shell.title) {
          shell.title.textContent = value;
        }
      },
      setLoading: function () {
        shell.body.innerHTML = '';
        const row = document.createElement('div');
        row.className = 'pax-modal-loading';
        row.textContent = strings.loading || 'Loading…';
        shell.body.appendChild(row);
      },
      setContent: function (content) {
        shell.body.innerHTML = '';
        if (content instanceof Node) {
          shell.body.appendChild(content);
        } else if (typeof content === 'string') {
          const paragraph = document.createElement('p');
          paragraph.textContent = content;
          shell.body.appendChild(paragraph);
        }
      },
    };
  }

  const confirmModal = (function () {
    const shell = createModalShell('pax-confirm-modal', strings.confirmTitle || 'Please confirm');
    const messageNode = document.createElement('p');
    shell.body.appendChild(messageNode);
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'btn muted';
    cancel.textContent = strings.cancel || 'Cancel';
    const confirm = document.createElement('button');
    confirm.type = 'button';
    confirm.className = 'btn primary';
    confirm.textContent = 'OK';
    shell.actions.appendChild(cancel);
    shell.actions.appendChild(confirm);
    cancel.addEventListener('click', shell.close);
    return {
      open(message, confirmLabel, onConfirm) {
        messageNode.textContent = message || '';
        if (confirmLabel) {
          confirm.textContent = confirmLabel;
        } else {
          confirm.textContent = 'OK';
        }
        confirm.onclick = function () {
          shell.close();
          if (typeof onConfirm === 'function') {
            onConfirm();
          }
        };
        shell.open();
      },
    };
  })();



  function focusInput() {
    if (input) {
      setTimeout(function () {
        input.focus();
      }, 60);
    }
  }

  // Chat animation helper functions with slide-from-bottom + subtle spin
  function openChatWithAnimation() {
    const animations = opts.chat_animations || {};
    const enabled = animations.enabled !== false;
    const duration = animations.duration || 300;
    const easing = animations.easing || 'ease';
    
    // Check for prefers-reduced-motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    if (enabled && !prefersReducedMotion) {
      // Add lifecycle class
      chat.classList.add('is-opening');
      
      // Set animation properties
      chat.style.transition = `all ${duration}ms ${easing}`;
      chat.style.pointerEvents = 'none';
      if (chatOverlay) {
        chatOverlay.style.transition = `opacity ${duration}ms ${easing}`;
      }
      
      // Initial state: slide from bottom with subtle spin
      chat.style.opacity = '0';
      chat.style.transform = 'translateY(24px) scale(0.98) rotate(-3deg)';
      
      // Trigger animation
      requestAnimationFrame(function() {
        chat.classList.add('is-open');
        chat.style.opacity = '1';
        chat.style.transform = 'translateY(0) scale(1) rotate(0deg)';
        
        if (chatOverlay) {
          chatOverlay.classList.add('open');
        }
      });
      
      // Clean up after animation with fallback
      const cleanup = function() {
        chat.classList.remove('is-opening');
        chat.style.transition = '';
        chat.style.opacity = '';
        chat.style.transform = '';
        chat.style.pointerEvents = '';
        if (chatOverlay) {
          chatOverlay.style.transition = '';
        }
      };
      
      // Use transitionend with timeout fallback
      let cleanupDone = false;
      chat.addEventListener('transitionend', function handler() {
        if (!cleanupDone) {
          cleanupDone = true;
          cleanup();
          chat.removeEventListener('transitionend', handler);
        }
      });
      
      setTimeout(function() {
        if (!cleanupDone) {
          cleanupDone = true;
          cleanup();
        }
      }, duration + 50);
    } else {
      chat.classList.add('is-open');
      if (chatOverlay) {
        chatOverlay.classList.add('open');
      }
    }
    
    focusInput();
  }
  
  function closeChatWithAnimation() {
    const animations = opts.chat_animations || {};
    const enabled = animations.enabled !== false;
    const duration = animations.duration || 300;
    const easing = animations.easing || 'ease';
    
    // Check for prefers-reduced-motion
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    if (enabled && !prefersReducedMotion) {
      // Add lifecycle class
      chat.classList.add('is-closing');
      
      // Set animation properties
      chat.style.transition = `all ${duration}ms ${easing}`;
      chat.style.pointerEvents = 'none';
      if (chatOverlay) {
        chatOverlay.style.transition = `opacity ${duration}ms ${easing}`;
      }
      
      // Trigger closing animation: slide down with subtle spin
      chat.style.opacity = '0';
      chat.style.transform = 'translateY(24px) scale(0.98) rotate(-3deg)';
      
      if (chatOverlay) {
        chatOverlay.classList.remove('open');
      }
      
      // Remove open class after animation with fallback
      const cleanup = function() {
        chat.classList.remove('is-open', 'is-closing');
        chat.style.transition = '';
        chat.style.opacity = '';
        chat.style.transform = '';
        chat.style.pointerEvents = '';
        if (chatOverlay) {
          chatOverlay.style.transition = '';
        }
      };
      
      // Use transitionend with timeout fallback
      let cleanupDone = false;
      chat.addEventListener('transitionend', function handler() {
        if (!cleanupDone) {
          cleanupDone = true;
          cleanup();
          chat.removeEventListener('transitionend', handler);
        }
      });
      
      setTimeout(function() {
        if (!cleanupDone) {
          cleanupDone = true;
          cleanup();
        }
      }, duration + 50);
    } else {
      chat.classList.remove('is-open');
      if (chatOverlay) {
        chatOverlay.classList.remove('open');
      }
    }
  }

  launcher.addEventListener('click', function () {
    // Check chat access control
    const reason = shouldBlockChat();
    if (reason === 'disabled') {
      showToast(strings.chatDisabled || 'Chat is currently disabled. Please try again later.');
      return;
    }
    if (reason === 'login_required') {
      showToast(strings.loginRequired || 'Please log in to use the chat system.');
      return;
    }
    
    const open = chat.classList.contains('open');
    if (opts.toggle_on_click) {
      if (open) {
        closeChatWithAnimation();
      } else {
        openChatWithAnimation();
      }
    } else if (!open) {
      openChatWithAnimation();
    }
  });

  // Hide menu button if chat menu is disabled
  if (opts.disable_chat_menu && menuBtn) {
    menuBtn.style.display = 'none';
  }

  if (closeBtn) {
    closeBtn.addEventListener('click', function () {
      closeChatWithAnimation();
    });
  }

  // Close on overlay click
  if (chatOverlay) {
    chatOverlay.addEventListener('click', function () {
      closeChatWithAnimation();
    });
  }

  // Close on ESC key
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && chat.classList.contains('open')) {
      closeChatWithAnimation();
    }
  });

  // Safe closest() helper to protect against broken overrides
  function safeClosest(element, selector) {
    if (!element) return null;
    if (typeof element.closest === 'function') {
      try {
        return element.closest(selector);
      } catch (e) {
        // Fallback if closest() is broken
      }
    }
    // Manual traversal fallback
    let current = element;
    while (current && current !== document) {
      if (current.matches && current.matches(selector)) {
        return current;
      }
      current = current.parentElement;
    }
    return null;
  }

  // ========================= MENU BUTTON HANDLING WITH SLIDER ANIMATION =========================
if (menuBtn && menu) {
  let menuAnimating = false;
  
  function openMenuWithAnimation() {
    if (menuAnimating) return;
    menuAnimating = true;
    
    const animations = opts.chat_animations || {};
    const enabled = animations.enabled !== false;
    const duration = animations.duration || 300;
    const easing = animations.easing || 'ease';
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    menu.style.display = 'flex';
    
    if (enabled && !prefersReducedMotion) {
      // Determine slide direction based on text direction
      const isRTL = document.dir === 'rtl' || document.documentElement.dir === 'rtl';
      const slideFrom = isRTL ? 'translateX(100%)' : 'translateX(-100%)';
      
      menu.style.transition = `all ${duration}ms ${easing}`;
      menu.style.transform = slideFrom;
      menu.style.opacity = '0';
      
      requestAnimationFrame(function() {
        menu.classList.add('open');
        menu.style.transform = 'translateX(0) scale(1)';
        menu.style.opacity = '1';
      });
      
      setTimeout(function() {
        menu.style.transition = '';
        menu.style.transform = '';
        menu.style.opacity = '';
        menuAnimating = false;
      }, duration + 50);
    } else {
      menu.classList.add('open');
      menuAnimating = false;
    }
  }
  
  function closeMenuWithAnimation() {
    if (menuAnimating) return;
    menuAnimating = true;
    
    const animations = opts.chat_animations || {};
    const enabled = animations.enabled !== false;
    const duration = animations.duration || 300;
    const easing = animations.easing || 'ease';
    const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    
    if (enabled && !prefersReducedMotion) {
      const isRTL = document.dir === 'rtl' || document.documentElement.dir === 'rtl';
      const slideTo = isRTL ? 'translateX(100%)' : 'translateX(-100%)';
      
      menu.style.transition = `all ${duration}ms ${easing}`;
      menu.style.transform = slideTo + ' scale(0.95)';
      menu.style.opacity = '0';
      
      setTimeout(function() {
        menu.classList.remove('open');
        menu.style.display = 'none';
        menu.style.transition = '';
        menu.style.transform = '';
        menu.style.opacity = '';
        menuAnimating = false;
      }, duration);
    } else {
      menu.classList.remove('open');
      menu.style.display = 'none';
      menuAnimating = false;
    }
  }
  
  menuBtn.addEventListener('click', function (event) {
    event.preventDefault();
    event.stopPropagation();
    const isOpen = menu.classList.contains('open');
    if (isOpen) {
      closeMenuWithAnimation();
    } else {
      openMenuWithAnimation();
    }
  });

  // Close menu when clicking outside
  document.addEventListener('click', function (event) {
    if (
      menu.classList.contains('open') &&
      !menu.contains(event.target) &&
      !menuBtn.contains(event.target)
    ) {
      closeMenuWithAnimation();
    }
  }, true);

  // Close menu on ESC key
  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && menu.classList.contains('open')) {
      closeMenuWithAnimation();
    }
  });
}


  // ChatGPT-style reaction icons functionality
  let activeReactionIcons = new Map(); // Track active reactions per message
  
  function createReactionIcon(type, label, messageId, messageText) {
    const icon = document.createElement('button');
    icon.className = 'pax-reaction-icon pax-reaction-' + type;
    icon.setAttribute('aria-label', label);
    icon.setAttribute('title', label);
    icon.setAttribute('data-message-id', messageId);
    icon.setAttribute('data-reaction-type', type);
    
    // SVG icons matching ChatGPT design
    let svgContent = '';
    if (type === 'copy') {
      svgContent = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>';
    } else if (type === 'like') {
      svgContent = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>';
    } else if (type === 'dislike') {
      svgContent = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"></path></svg>';
    }
    icon.innerHTML = svgContent;
    
    // Handle click events
    icon.addEventListener('click', function(e) {
      e.stopPropagation();
      handleReactionClick(type, messageId, messageText, icon);
    });
    
    return icon;
  }
  
  function handleReactionClick(type, messageId, messageText, iconElement) {
    const reactionSettings = opts.chat_reactions || {};
    
    if (type === 'copy') {
      // Copy functionality
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(messageText).then(function() {
          showToast(strings.copiedToClipboard || 'Copied to clipboard');
          iconElement.classList.add('active');
          setTimeout(function() {
            iconElement.classList.remove('active');
          }, 1000);
        }).catch(function() {
          showToast('Failed to copy');
        });
      } else {
        // Fallback
        const textarea = document.createElement('textarea');
        textarea.value = messageText;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
          document.execCommand('copy');
          showToast(strings.copiedToClipboard || 'Copied to clipboard');
          iconElement.classList.add('active');
          setTimeout(function() {
            iconElement.classList.remove('active');
          }, 1000);
        } catch (err) {
          showToast('Failed to copy');
        }
        document.body.removeChild(textarea);
      }
    } else if (type === 'like' || type === 'dislike') {
      // Toggle reaction state
      const isActive = iconElement.classList.contains('active');
      
      if (isActive) {
        iconElement.classList.remove('active');
        activeReactionIcons.delete(messageId + '-' + type);
      } else {
        iconElement.classList.add('active');
        activeReactionIcons.set(messageId + '-' + type, true);
        
        // If like/dislike, remove opposite reaction
        const oppositeType = type === 'like' ? 'dislike' : 'like';
        const oppositeKey = messageId + '-' + oppositeType;
        if (activeReactionIcons.has(oppositeKey)) {
          const oppositeIcon = document.querySelector('[data-message-id="' + messageId + '"][data-reaction-type="' + oppositeType + '"]');
          if (oppositeIcon) {
            oppositeIcon.classList.remove('active');
          }
          activeReactionIcons.delete(oppositeKey);
        }
      }
      
      // Save to database
      saveReactionToDatabase(messageId, type, !isActive, messageText);
      
      // Show feedback
      if (!isActive) {
        showToast(type === 'like' ? (strings.liked || 'Liked!') : (strings.feedbackNoted || 'Feedback noted'));
      }
    }
  }
  
  function saveReactionToDatabase(messageId, reactionType, isActive, messageText) {
    if (!rest.reactions) {
      return;
    }
    
    const data = {
      message_id: messageId,
      reaction_type: reactionType,
      is_active: isActive ? 1 : 0,
      message_text: messageText,
      session_id: sessionId,
      user_id: user.ID || 0
    };
    
    fetch(rest.reactions, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
      },
      body: JSON.stringify(data)
    }).catch(function(err) {
      console.error('Failed to save reaction:', err);
    });
  }

  function appendMessage(role, text) {
    const row = document.createElement('div');
    row.className = 'pax-msg ' + role;
    const messageId = 'msg-' + Date.now() + '-' + Math.random().toString(36).substr(2, 9);
    row.setAttribute('data-message-id', messageId);
    
    const span = document.createElement('span');
    span.textContent = text;
    row.appendChild(span);
    
    // Add ChatGPT-style reaction icons for bot messages
    if (role === 'bot') {
      const reactionSettings = opts.chat_reactions || {};
      const iconsContainer = document.createElement('div');
      iconsContainer.className = 'pax-reaction-icons';
      
      // Copy icon (if enabled)
      if (reactionSettings.enable_copy !== false) {
        const copyIcon = createReactionIcon('copy', strings.copy || 'Copy', messageId, text);
        iconsContainer.appendChild(copyIcon);
      }
      
      // Like icon (if enabled)
      if (reactionSettings.enable_like !== false) {
        const likeIcon = createReactionIcon('like', strings.like || 'Like', messageId, text);
        iconsContainer.appendChild(likeIcon);
      }
      
      // Dislike icon (if enabled)
      if (reactionSettings.enable_dislike !== false) {
        const dislikeIcon = createReactionIcon('dislike', strings.dislike || 'Dislike', messageId, text);
        iconsContainer.appendChild(dislikeIcon);
      }
      
      if (iconsContainer.children.length > 0) {
        row.appendChild(iconsContainer);
      }
    }
    
    log.appendChild(row);
    log.scrollTop = log.scrollHeight;
  }

  function renderSuggestions(items) {
    if (!suggestionsWrap) {
      return;
    }
    suggestionsWrap.innerHTML = '';
    if (!items || !items.length) {
      suggestionsWrap.classList.remove('show');
      return;
    }
    const title = document.createElement('div');
    title.className = 'pax-suggestions-title';
    title.textContent = strings.aiSuggestions || 'Suggestions';
    suggestionsWrap.appendChild(title);

    const list = document.createElement('ul');
    list.className = 'pax-suggestions-list';

    items.forEach(function (item) {
      if (!item || !item.url) {
        return;
      }
      const li = document.createElement('li');
      li.className = 'pax-suggestion-item';
      const link = document.createElement('a');
      link.href = item.url;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      link.textContent = item.title || item.url;
      li.appendChild(link);
      if (item.summary) {
        const summary = document.createElement('p');
        summary.textContent = item.summary;
        li.appendChild(summary);
      }
      list.appendChild(li);
    });

    suggestionsWrap.appendChild(list);
    suggestionsWrap.classList.add('show');
  }

  function setTyping(active) {
    let typingRow = document.getElementById('pax-typing');
    if (active && !typingRow) {
      typingRow = document.createElement('div');
      typingRow.id = 'pax-typing';
      typingRow.className = 'pax-msg bot';
      typingRow.innerHTML = '<div class="pax-ai-robot-typing">' +
        '<svg class="pax-robot-icon" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">' +
          '<defs>' +
            '<linearGradient id="robotGrad" x1="0%" y1="0%" x2="100%" y2="100%">' +
              '<stop offset="0%" style="stop-color:var(--pax-accent);stop-opacity:1" />' +
              '<stop offset="100%" style="stop-color:#ff6f60;stop-opacity:1" />' +
            '</linearGradient>' +
            '<filter id="glow">' +
              '<feGaussianBlur stdDeviation="2" result="coloredBlur"/>' +
              '<feMerge>' +
                '<feMergeNode in="coloredBlur"/>' +
                '<feMergeNode in="SourceGraphic"/>' +
              '</feMerge>' +
            '</filter>' +
          '</defs>' +
          '<circle cx="32" cy="32" r="28" fill="url(#robotGrad)" opacity="0.2" class="robot-bg"/>' +
          '<rect x="18" y="20" width="28" height="24" rx="4" fill="url(#robotGrad)" filter="url(#glow)" class="robot-head"/>' +
          '<circle cx="25" cy="30" r="3" fill="#fff" class="robot-eye robot-eye-left"/>' +
          '<circle cx="39" cy="30" r="3" fill="#fff" class="robot-eye robot-eye-right"/>' +
          '<rect x="24" y="38" width="16" height="2" rx="1" fill="#fff" opacity="0.8" class="robot-mouth"/>' +
          '<rect x="14" y="28" width="4" height="8" rx="2" fill="url(#robotGrad)" class="robot-antenna-left"/>' +
          '<rect x="46" y="28" width="4" height="8" rx="2" fill="url(#robotGrad)" class="robot-antenna-right"/>' +
          '<circle cx="16" cy="26" r="2" fill="var(--pax-accent)" class="robot-antenna-tip-left"/>' +
          '<circle cx="48" cy="26" r="2" fill="var(--pax-accent)" class="robot-antenna-tip-right"/>' +
        '</svg>' +
        '<span class="pax-typing-text">AI is thinking...</span>' +
      '</div>';
      log.appendChild(typingRow);
    } else if (!active && typingRow) {
      typingRow.remove();
    }
    log.scrollTop = log.scrollHeight;
  }

  async function sendMessage() {
    if (!input) {
      return;
    }
    const text = (input.value || '').trim();
    if (!text) {
      return;
    }
    appendMessage('user', text);
    input.value = '';
    historyStack.push({ role: 'user', content: text });
    if (historyStack.length > 10) {
      historyStack.splice(0, historyStack.length - 10);
    }

    if (!aiEnabled || !aiEndpoint) {
      appendMessage('bot', strings.aiOffline || strings.noResponse || 'Assistant is offline.');
      showToast(strings.aiOffline || 'Assistant is offline.');
      renderSuggestions([]);
      return;
    }

    showToast(strings.aiThinking || 'Assistant is thinking…');
    setTyping(true);

    try {
      const response = await restFetch(aiEndpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: text,
          history: historyStack.slice(-6),
          session: sessionId,
          lang: userLocale,
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });

      if (data && data.session) {
        sessionId = data.session;
        try {
          if (window.sessionStorage) {
            window.sessionStorage.setItem('pax-ai-session', sessionId);
          }
        } catch (err) {
          // Ignore storage errors.
        }
      }

      const suggestions = data && data.suggestions ? data.suggestions : [];

      if (!response.ok) {
        setTyping(false);
        renderSuggestions(suggestions);
        if (data && data.reply) {
          appendMessage('bot', data.reply);
        } else {
          appendMessage('bot', strings.noResponse || 'No response.');
        }
        return;
      }

      if (data && data.status === 'offline') {
        setTyping(false);
        renderSuggestions(suggestions);
        showToast(strings.aiOffline || 'Assistant is offline.');
        if (data.reply) {
          appendMessage('bot', data.reply);
        }
        return;
      }

      if (data && data.status === 'rate_limited') {
        setTyping(false);
        renderSuggestions(suggestions);
        if (data.reply) {
          appendMessage('bot', data.reply);
        }
        return;
      }

      const reply = data && data.reply ? data.reply : strings.noResponse || 'No response.';
      const delay = Math.min(3200, Math.max(600, reply.length * 25));
      renderSuggestions(suggestions);
      setTimeout(function () {
        setTyping(false);
        appendMessage('bot', reply);
        historyStack.push({ role: 'assistant', content: reply });
        if (historyStack.length > 10) {
          historyStack.splice(0, historyStack.length - 10);
        }
        showToast(strings.aiResponding || 'Assistant responded.');
      }, delay);
    } catch (error) {
      setTyping(false);
      renderSuggestions([]);
      appendMessage('bot', strings.networkError || 'Network error.');
    }
  }

  if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
  }

  if (input) {
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        sendMessage();
      }
    });
  }

  // Show welcome message with animation for logged-in users
  if (isLoggedIn && opts.welcome_message) {
    const welcomeMsg = document.createElement('div');
    welcomeMsg.className = 'pax-msg bot pax-welcome-msg';
    welcomeMsg.innerHTML = '<div class="pax-welcome-content">' +
      '<div class="pax-welcome-icon">' +
        '<svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">' +
          '<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" fill="var(--pax-accent)"/>' +
        '</svg>' +
      '</div>' +
      '<div class="pax-welcome-text">' + escapeHtml(opts.welcome_message) + '</div>' +
    '</div>';
    log.appendChild(welcomeMsg);
    setTimeout(function() {
      welcomeMsg.classList.add('show');
    }, 100);
  } else {
    appendMessage('bot', strings.welcome || 'Welcome!');
  }
  
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

  function refreshOffline() {
    const offline = !!opts.enable_offline_guard && !navigator.onLine;
    if (offlineBadge) {
      offlineBadge.classList.toggle('on', offline);
    }
  }

  window.addEventListener('online', refreshOffline);
  window.addEventListener('offline', refreshOffline);
  refreshOffline();

  async function updateCooldown() {
    if (!rest.cooldown) {
      return;
    }
    const badgeContainer = document.querySelector('.pax-item[data-act="ticket"]');
    if (!badgeContainer) {
      return;
    }
    try {
      const response = await restFetch(rest.cooldown);
      const data = await response.json().catch(function () {
        return {};
      });
      const existing = badgeContainer.querySelector('.pax-badge');
      if (existing) {
        existing.remove();
      }
      const prevActive = cooldownInfo.active;
      cooldownInfo.active = false;
      cooldownInfo.until = 0;
      if (!data || !data.enabled) {
        return;
      }
      const until = parseInt(data.until, 10) || 0;
      const secondsTotal = parseInt(data.seconds, 10) || 0;
      if (!secondsTotal) {
        return;
      }
      cooldownInfo.active = secondsTotal > 0;
      cooldownInfo.until = until;
      if (cooldownInfo.active && !prevActive) {
        showToast(strings.ticketCooldownNotice || strings.ticketCooldownActive || 'Cooldown active.');
      }
      const badge = document.createElement('span');
      badge.className = 'pax-badge';
      badgeContainer.appendChild(badge);
      function tick() {
        const now = Math.floor(Date.now() / 1000);
        const remaining = Math.max(0, until - now);
        if (remaining <= 0) {
          badge.remove();
          clearInterval(timer);
          cooldownInfo.active = false;
          cooldownInfo.until = 0;
          return;
        }
        let seconds = remaining;
        const days = Math.floor(seconds / 86400);
        seconds %= 86400;
        const hours = Math.floor(seconds / 3600);
        seconds %= 3600;
        const minutes = Math.floor(seconds / 60);
        seconds %= 60;
        badge.textContent = (strings.cooldown || 'Cooldown') + ' ' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
      }
      tick();
      const timer = setInterval(tick, 1000);
    } catch (error) {
      // ignore fetch issues
    }
  }
  updateCooldown();

  const agentModal = buildModal(
    'pax-agent-modal',
    strings.agentTitle || 'Live Agent',
    [
      { tag: 'input', id: 'pax-agent-name', placeholder: strings.agentName || 'Your name *', autocomplete: 'name' },
      { tag: 'input', id: 'pax-agent-email', type: 'email', placeholder: strings.agentEmail || 'Your email *', autocomplete: 'email' },
      { tag: 'textarea', id: 'pax-agent-issue', rows: 4, placeholder: strings.agentIssue || 'Briefly describe your issue *' },
    ],
    strings.agentSubmit || 'Send'
  );

  agentModal.submit.addEventListener('click', async function () {
    const values = agentModal.getValues();
    if (!values['pax-agent-name'] || !values['pax-agent-email'] || !values['pax-agent-issue']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    agentModal.setLoading(strings.sending || 'Sending…');
    try {
      const response = await restFetch(rest.agent, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: values['pax-agent-name'],
          email: values['pax-agent-email'],
          issue: values['pax-agent-issue'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      agentModal.resetSubmit();
      if (response.ok && data && data.ok) {
        agentModal.close();
        showToast(strings.agentSuccess || 'Sent to live agent');
      } else {
        showToast((strings.agentError || 'Failed: ') + (data && data.message ? data.message : 'Error'));
      }
    } catch (error) {
      agentModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  const callbackModal = buildModal(
    'pax-callback-modal',
    strings.scheduleTitle || strings.callbackTitle || 'Schedule a Callback',
    [
      { tag: 'input', id: 'pax-cb-name', placeholder: strings.callbackName || 'Your name *', autocomplete: 'name' },
      { tag: 'input', id: 'pax-cb-phone', placeholder: strings.callbackPhone || 'Phone/WhatsApp *', autocomplete: 'tel' },
      { tag: 'input', id: 'pax-cb-date', type: 'date', placeholder: strings.scheduleDate || 'Preferred date *', required: true },
      { tag: 'input', id: 'pax-cb-time', type: 'time', step: '900', placeholder: strings.scheduleTime || 'Preferred time *', required: true },
      { tag: 'textarea', id: 'pax-cb-note', rows: 3, placeholder: strings.scheduleNote || 'Add a short note (optional)' },
      { tag: 'input', id: 'pax-cb-timezone', type: 'hidden' },
    ],
    strings.scheduleSubmit || strings.callbackSubmit || 'Book callback'
  );

  const scheduleInfoWrap = document.createElement('div');
  scheduleInfoWrap.className = 'pax-schedule-info-wrap';
  const scheduleTimezoneInfo = document.createElement('p');
  scheduleTimezoneInfo.className = 'pax-schedule-info';
  const scheduleHoursInfo = document.createElement('p');
  scheduleHoursInfo.className = 'pax-schedule-info';
  scheduleInfoWrap.appendChild(scheduleTimezoneInfo);
  scheduleInfoWrap.appendChild(scheduleHoursInfo);
  callbackModal.body.appendChild(scheduleInfoWrap);

  const scheduleListTitle = document.createElement('h4');
  scheduleListTitle.className = 'pax-schedule-heading';
  scheduleListTitle.textContent = strings.scheduleListTitle || 'Your scheduled callbacks';
  callbackModal.body.appendChild(scheduleListTitle);

  const scheduleList = document.createElement('div');
  scheduleList.className = 'pax-schedule-list';
  callbackModal.body.appendChild(scheduleList);

  function detectTimezone() {
    if (scheduler.timezone) {
      return scheduler.timezone;
    }
    try {
      const intlZone = Intl.DateTimeFormat().resolvedOptions().timeZone;
      return intlZone || 'UTC';
    } catch (error) {
      return 'UTC';
    }
  }

  function updateScheduleTimezone() {
    const tz = detectTimezone();
    const field = callbackModal.body.querySelector('#pax-cb-timezone');
    if (field) {
      field.value = tz;
    }
    if (scheduleTimezoneInfo) {
      const template = strings.scheduleTimezone || 'Detected timezone: %s';
      scheduleTimezoneInfo.textContent = template.replace('%s', tz);
    }
    if (scheduleHoursInfo) {
      const start = scheduler.hours && scheduler.hours.start ? scheduler.hours.start : '09:00';
      const end = scheduler.hours && scheduler.hours.end ? scheduler.hours.end : '17:00';
      const template = strings.scheduleWorkingHours || 'Available hours: %1$s – %2$s';
      scheduleHoursInfo.textContent = template.replace('%1$s', start).replace('%2$s', end);
    }
  }

  function formatScheduleTimestamp(item) {
    if (item.timestamp) {
      try {
        return new Intl.DateTimeFormat(undefined, {
          dateStyle: 'medium',
          timeStyle: 'short',
          timeZone: item.timezone || scheduler.timezone || undefined,
        }).format(new Date(item.timestamp * 1000));
      } catch (error) {
        return new Date(item.timestamp * 1000).toLocaleString();
      }
    }
    return (item.date || '') + ' ' + (item.time || '');
  }

  function getCancelEndpoint(id) {
    if (scheduleBase) {
      return scheduleBase + id + '/cancel';
    }
    if (rest.schedule) {
      return rest.schedule.replace(/\/?$/, '/') + id + '/cancel';
    }
    return '';
  }

  function renderScheduleList(items) {
    scheduleList.innerHTML = '';
    if (!items || !items.length) {
      const empty = document.createElement('p');
      empty.className = 'pax-schedule-empty';
      empty.textContent = strings.scheduleListEmpty || 'No callbacks scheduled yet.';
      scheduleList.appendChild(empty);
      return;
    }
    items.forEach(function (item) {
      const card = document.createElement('div');
      card.className = 'pax-schedule-card';
      const headline = document.createElement('div');
      headline.className = 'pax-schedule-datetime';
      headline.textContent = formatScheduleTimestamp(item);
      card.appendChild(headline);
      const status = document.createElement('span');
      status.className = 'pax-schedule-status';
      status.textContent = (item.status || '').toUpperCase();
      card.appendChild(status);
      if (item.contact) {
        const contactRow = document.createElement('p');
        contactRow.className = 'pax-schedule-contact';
        contactRow.textContent = item.contact;
        card.appendChild(contactRow);
      }
      if (item.note) {
        const noteRow = document.createElement('p');
        noteRow.className = 'pax-schedule-note';
        noteRow.textContent = item.note;
        card.appendChild(noteRow);
      }
      if (item.status !== 'canceled' && item.status !== 'done') {
        const actions = document.createElement('div');
        actions.className = 'pax-schedule-actions';
        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn muted';
        cancelBtn.textContent = strings.scheduleCancel || 'Cancel';
        cancelBtn.addEventListener('click', function () {
          confirmModal.open(strings.scheduleCancelConfirm || 'Cancel this callback?', strings.scheduleCancel || 'Cancel', async function () {
            const endpoint = getCancelEndpoint(item.id);
            if (!endpoint) {
              showToast(strings.networkError || 'Network error.');
              return;
            }
            try {
              const response = await restFetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' } });
              if (response.ok) {
                showToast(strings.scheduleCancelSuccess || 'Callback canceled.');
                fetchSchedules();
              } else {
                showToast(strings.scheduleError || 'Unable to schedule callback.');
              }
            } catch (error) {
              showToast(strings.networkError || 'Network error.');
            }
          });
        });
        actions.appendChild(cancelBtn);
        card.appendChild(actions);
      }
      scheduleList.appendChild(card);
    });
  }

  async function fetchSchedules() {
    if (!rest.schedule) {
      renderScheduleList([]);
      return;
    }
    scheduleList.innerHTML = '';
    const loading = document.createElement('p');
    loading.className = 'pax-schedule-loading';
    loading.textContent = strings.loading || 'Loading…';
    scheduleList.appendChild(loading);
    try {
      const response = await restFetch(rest.schedule, { method: 'GET' });
      if (!response.ok) {
        throw new Error('Request failed');
      }
      const data = await response.json().catch(function () {
        return {};
      });
      renderScheduleList(Array.isArray(data.items) ? data.items : []);
    } catch (error) {
      renderScheduleList([]);
    }
  }

  updateScheduleTimezone();

  function openScheduleModal() {
    const nameField = callbackModal.body.querySelector('#pax-cb-name');
    if (nameField && user.name && !nameField.value) {
      nameField.value = user.name;
    }
    const dateField = callbackModal.body.querySelector('#pax-cb-date');
    if (dateField && !dateField.value) {
      const today = new Date();
      dateField.value = today.toISOString().slice(0, 10);
    }
    const timeField = callbackModal.body.querySelector('#pax-cb-time');
    if (timeField && !timeField.value) {
      const slot = new Date();
      const remainder = slot.getMinutes() % 30;
      if (remainder !== 0) {
        slot.setMinutes(slot.getMinutes() + (30 - remainder));
      } else {
        slot.setMinutes(slot.getMinutes() + 30);
      }
      slot.setSeconds(0, 0);
      const hours = String(slot.getHours()).padStart(2, '0');
      const minutes = String(slot.getMinutes()).padStart(2, '0');
      timeField.value = hours + ':' + minutes;
    }
    updateScheduleTimezone();
    fetchSchedules();
    callbackModal.open();
  }

  callbackModal.submit.addEventListener('click', async function () {
    const values = callbackModal.getValues();
    if (!values['pax-cb-name'] || !values['pax-cb-phone']) {
      showToast(strings.callbackRequired || 'Name & phone required.');
      return;
    }
    if (!values['pax-cb-date'] || !values['pax-cb-time']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    if (!rest.schedule) {
      showToast(strings.networkError || 'Network error.');
      return;
    }
    callbackModal.setLoading(strings.sending || 'Sending…');
    const tzField = callbackModal.body.querySelector('#pax-cb-timezone');
    const tzValue = tzField && tzField.value ? tzField.value : detectTimezone();
    const contactValue = values['pax-cb-phone']
      ? (values['pax-cb-name'] ? values['pax-cb-name'] + ' — ' + values['pax-cb-phone'] : values['pax-cb-phone'])
      : '';
    try {
      const response = await restFetch(rest.schedule, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: values['pax-cb-name'],
          contact: contactValue,
          phone: values['pax-cb-phone'],
          date: values['pax-cb-date'],
          time: values['pax-cb-time'],
          timezone: tzValue,
          note: values['pax-cb-note'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      callbackModal.resetSubmit();
      if (response.ok && data && data.ok) {
        showToast(strings.scheduleSuccess || 'Callback booked successfully.');
        const noteField = callbackModal.body.querySelector('#pax-cb-note');
        if (noteField) {
          noteField.value = '';
        }
        fetchSchedules();
      } else {
        showToast((strings.scheduleError || 'Unable to schedule callback.') + (data && data.message ? ' ' + data.message : ''));
      }
    } catch (error) {
      callbackModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  const ticketModal = buildModal(
    'pax-ticket-modal',
    strings.ticketTitle || 'Create Support Ticket',
    [
      { tag: 'input', id: 'pax-ticket-name', placeholder: strings.ticketName || 'Your name *', autocomplete: 'name' },
      { tag: 'input', id: 'pax-ticket-email', type: 'email', placeholder: strings.ticketEmail || 'Your email *', autocomplete: 'email' },
      { tag: 'input', id: 'pax-ticket-subject', placeholder: strings.ticketSubject || 'Subject *' },
      { tag: 'textarea', id: 'pax-ticket-message', rows: 5, placeholder: strings.ticketMessage || 'Describe your request *' },
    ],
    strings.ticketSubmit || 'Submit Ticket'
  );

  function openTicketModal() {
    const nameField = ticketModal.body.querySelector('#pax-ticket-name');
    if (nameField && user.name && !nameField.value) {
      nameField.value = user.name;
    }
    const emailField = ticketModal.body.querySelector('#pax-ticket-email');
    if (emailField && user.email && !emailField.value) {
      emailField.value = user.email;
    }
    if (cooldownInfo.active) {
      showToast(strings.ticketCooldownActive || strings.ticketCooldownNotice || 'Cooldown active.');
    }
    ticketModal.open();
  }

  ticketModal.submit.addEventListener('click', async function () {
    const values = ticketModal.getValues();
    if (!values['pax-ticket-name'] || !values['pax-ticket-email'] || !values['pax-ticket-subject'] || !values['pax-ticket-message']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    ticketModal.setLoading(strings.sending || 'Sending…');
    try {
      const response = await restFetch(rest.ticket, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          name: values['pax-ticket-name'],
          email: values['pax-ticket-email'],
          subject: values['pax-ticket-subject'],
          message: values['pax-ticket-message'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      ticketModal.resetSubmit();
      if (response.ok && data && data.ok) {
        ticketModal.close();
        showToast(strings.ticketSuccess || 'Ticket submitted successfully.');
        updateCooldown();
      } else if (response.status === 429) {
        showToast((data && data.message) ? data.message : (strings.ticketCooldownActive || 'Cooldown active.'));
        updateCooldown();
      } else {
        showToast((strings.ticketError || 'Unable to submit ticket.') + (data && data.message ? ' ' + data.message : ''));
      }
    } catch (error) {
      ticketModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  function createKnowledgeModal() {
    const shell = createModalShell('pax-kb-modal', strings.kbTitle || strings.helpTitle || 'Knowledge Base');
    if (shell.actions) {
      shell.actions.style.display = 'none';
    }

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'pax-kb-search';
    search.placeholder = strings.kbSearchPlaceholder || 'Search articles…';

    const status = document.createElement('div');
    status.className = 'pax-kb-status';

    const results = document.createElement('div');
    results.className = 'pax-kb-results';

    shell.body.appendChild(search);
    shell.body.appendChild(status);
    shell.body.appendChild(results);

    function setStatus(text) {
      status.textContent = text || '';
      status.style.display = text ? 'block' : 'none';
    }

    function render(items) {
      results.innerHTML = '';
      if (!items || !items.length) {
        setStatus(strings.kbEmpty || 'No matching knowledge base articles.');
        return;
      }
      setStatus('');
      items.forEach(function (item) {
        if (!item) {
          return;
        }
        const card = document.createElement('article');
        card.className = 'pax-kb-item';
        const heading = document.createElement('h4');
        heading.textContent = item.title || '';
        card.appendChild(heading);
        if (item.summary) {
          const summary = document.createElement('p');
          summary.textContent = item.summary;
          card.appendChild(summary);
        }
        if (item.url) {
          const link = document.createElement('a');
          link.href = item.url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.textContent = strings.kbView || 'Open article';
          link.className = 'pax-kb-link';
          card.appendChild(link);
        }
        results.appendChild(card);
      });
    }

    async function performSearch(query) {
      if (!rest.knowledge) {
        setStatus(strings.helpEmpty || 'No help articles were found.');
        results.innerHTML = '';
        return;
      }
      setStatus(strings.kbLoading || 'Searching knowledge base…');
      results.innerHTML = '';
      try {
        const connector = rest.knowledge.indexOf('?') === -1 ? '?' : '&';
        const endpoint = rest.knowledge + connector + 'lang=' + encodeURIComponent(userLocale || '') + (query ? '&q=' + encodeURIComponent(query) : '');
        const response = await restFetch(endpoint);
        const data = await response.json().catch(function () {
          return {};
        });
        if (response.ok && data && Array.isArray(data.articles) && data.articles.length) {
          render(data.articles);
        } else {
          render([]);
        }
      } catch (error) {
        setStatus(strings.networkError || 'Network error.');
      }
    }

    let timer = null;
    search.addEventListener('input', function () {
      const value = search.value.trim();
      clearTimeout(timer);
      timer = setTimeout(function () {
        performSearch(value);
      }, 260);
    });

    return {
      open: function () {
        shell.open();
        setTimeout(function () {
          search.focus();
        }, 80);
        performSearch(search.value.trim());
      },
      close: shell.close,
    };
  }

  const knowledgeModal = createKnowledgeModal();

  function openKnowledgeModal() {
    if (!rest.knowledge) {
      if (links.help) {
        window.open(links.help, '_blank', 'noopener,noreferrer');
      }
      return;
    }
    knowledgeModal.open();
  }

  const diagnosticsModal = buildInfoModal('pax-diagnostics-modal', strings.diagTitle || 'Diagnostics');

  async function openDiagnosticsModal() {
    if (!rest.diagnostics) {
      diagnosticsModal.setContent(strings.networkError || 'Network error.');
      diagnosticsModal.open();
      return;
    }
    diagnosticsModal.setLoading();
    diagnosticsModal.open();
    try {
      const response = await restFetch(rest.diagnostics);
      const data = await response.json().catch(function () {
        return {};
      });
      if (response.ok && data && data.items) {
        const list = document.createElement('ul');
        Object.keys(data.items).forEach(function (key) {
          const item = document.createElement('li');
          const heading = document.createElement('strong');
          heading.textContent = key;
          item.appendChild(heading);
          const value = document.createElement('p');
          value.textContent = data.items[key];
          item.appendChild(value);
          list.appendChild(item);
        });
        diagnosticsModal.setContent(list);
      } else {
        diagnosticsModal.setContent(strings.networkError || 'Network error.');
      }
    } catch (error) {
      diagnosticsModal.setContent(strings.networkError || 'Network error.');
    }
  }

  const troubleModal = buildModal(
    'pax-troubleshooter-modal',
    strings.troubleTitle || 'Troubleshooter',
    [
      {
        tag: 'select',
        id: 'pax-trouble-topic',
        options: [
          { value: '', label: strings.troubleSelect || 'Select an issue *', disabled: true, selected: true },
          { value: 'performance', label: strings.troublePerformance || 'Performance issues' },
          { value: 'errors', label: strings.troubleErrors || 'Errors & crashes' },
          { value: 'billing', label: strings.troubleBilling || 'Billing & payments' },
          { value: 'other', label: strings.troubleOther || 'Other' },
        ],
      },
      { tag: 'textarea', id: 'pax-trouble-notes', rows: 4, placeholder: strings.troublePrompt || 'Describe the issue you are facing *' },
    ],
    strings.troubleSubmit || 'Run Troubleshooter'
  );

  const troubleResultModal = buildInfoModal('pax-trouble-result', strings.troubleTitle || 'Troubleshooter');

  troubleModal.submit.addEventListener('click', async function () {
    const values = troubleModal.getValues();
    if (!values['pax-trouble-topic'] || !values['pax-trouble-notes']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    if (!rest.trouble) {
      showToast(strings.networkError || 'Network error.');
      return;
    }
    troubleModal.setLoading(strings.sending || 'Sending…');
    try {
      const response = await restFetch(rest.trouble, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          topic: values['pax-trouble-topic'],
          notes: values['pax-trouble-notes'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      troubleModal.resetSubmit();
      troubleModal.close();
      if (response.ok && data && Array.isArray(data.steps) && data.steps.length) {
        const list = document.createElement('ol');
        data.steps.forEach(function (step) {
          const item = document.createElement('li');
          item.textContent = step;
          list.appendChild(item);
        });
        troubleResultModal.setContent(list);
        troubleResultModal.open();
      } else {
        showToast((strings.troubleError || 'No steps available.') + (data && data.message ? ' ' + data.message : ''));
      }
    } catch (error) {
      troubleModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  const orderModal = buildModal(
    'pax-order-modal',
    strings.orderTitle || 'Order Lookup',
    [
      { tag: 'input', id: 'pax-order-id', placeholder: strings.orderId || 'Order ID *' },
      { tag: 'input', id: 'pax-order-email', type: 'email', placeholder: strings.orderEmail || 'Billing email *', autocomplete: 'email' },
    ],
    strings.orderSubmit || 'Check Status'
  );

  const orderResultModal = buildInfoModal('pax-order-result', strings.orderTitle || 'Order Lookup');

  orderModal.submit.addEventListener('click', async function () {
    const values = orderModal.getValues();
    if (!values['pax-order-id'] || !values['pax-order-email']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    if (!rest.order) {
      showToast(strings.networkError || 'Network error.');
      return;
    }
    orderModal.setLoading(strings.sending || 'Sending…');
    try {
      const response = await restFetch(rest.order, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          order_id: values['pax-order-id'],
          email: values['pax-order-email'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      orderModal.resetSubmit();
      if (response.ok && data && data.ok) {
        orderModal.close();
        const info = document.createElement('p');
        info.textContent = data.message || strings.orderMissing || 'Order not found. Please verify the details.';
        orderResultModal.setContent(info);
        orderResultModal.open();
      } else {
        showToast(data && data.message ? data.message : strings.orderMissing || 'Order not found. Please verify the details.');
      }
    } catch (error) {
      orderModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  const myRequestModal = buildInfoModal('pax-myreq-modal', strings.myRequestTitle || 'My Requests');
  const ticketDetailModal = buildInfoModal('pax-ticket-detail', strings.ticketDetailTitle || 'Ticket details');

  async function fetchTicketList() {
    const endpoint = rest.tickets || rest.my_request;
    if (!endpoint) {
      return [];
    }
    try {
      const response = await restFetch(endpoint);
      const data = await response.json().catch(function () {
        return {};
      });
      if (response.ok && data && Array.isArray(data.items)) {
        return data.items;
      }
    } catch (error) {
      // ignore
    }
    return [];
  }

  async function loadTicketList() {
    const items = await fetchTicketList();
    renderTicketList(items);
  }

  function renderTicketList(items) {
    if (!items || !items.length) {
      myRequestModal.setContent(strings.ticketEmptyList || strings.myRequestEmpty || 'No tickets yet.');
      return;
    }
    const wrap = document.createElement('div');
    wrap.className = 'pax-ticket-list';
    items.forEach(function (item) {
      const row = document.createElement('div');
      row.className = 'pax-ticket-row';
      row.dataset.id = item.id;

      const subject = document.createElement('div');
      subject.className = 'subject';
      subject.textContent = item.subject || '';
      row.appendChild(subject);

      const meta = document.createElement('div');
      meta.className = 'meta';
      const statusLabel = strings.ticketStatusLabel || 'Status';
      const updatedLabel = strings.ticketUpdatedLabel || 'Updated';
      meta.textContent = statusLabel + ': ' + (item.status || '') + ' · ' + updatedLabel + ': ' + (item.updated || '');
      row.appendChild(meta);

      const actions = document.createElement('div');
      actions.className = 'actions';
      const viewBtn = document.createElement('button');
      viewBtn.type = 'button';
      viewBtn.className = 'btn link pax-ticket-view';
      viewBtn.dataset.id = item.id;
      viewBtn.textContent = strings.ticketViewButton || 'View';
      actions.appendChild(viewBtn);
      const deleteBtn = document.createElement('button');
      deleteBtn.type = 'button';
      deleteBtn.className = 'btn link pax-ticket-delete';
      deleteBtn.dataset.id = item.id;
      deleteBtn.textContent = strings.ticketDeleteButton || 'Delete';
      actions.appendChild(deleteBtn);
      row.appendChild(actions);

      wrap.appendChild(row);
    });

    wrap.addEventListener('click', function (event) {
      const button = safeClosest(event.target, 'button');
      if (!button) {
        return;
      }
      const id = parseInt(button.dataset.id, 10);
      if (!id) {
        return;
      }
      if (button.classList.contains('pax-ticket-view')) {
        openTicketDetail(id);
        return;
      }
      if (button.classList.contains('pax-ticket-delete')) {
        confirmModal.open(strings.ticketDeleteConfirm || 'Delete this ticket?', strings.ticketDeleteButton || 'Delete', function () {
          deleteTicket(id);
        });
      }
    });

    myRequestModal.setContent(wrap);
  }

  async function openTicketDetail(id) {
    if (!rest.ticket) {
      return;
    }
    ticketDetailModal.setTitle(strings.ticketDetailTitle || 'Ticket details');
    ticketDetailModal.setLoading();
    ticketDetailModal.open();
    try {
      const response = await restFetch(rest.ticket + '/' + id);
      const data = await response.json().catch(function () {
        return {};
      });
      if (response.ok && data && data.subject) {
        if (data.subject) {
          ticketDetailModal.setTitle(data.subject);
        }
        const container = document.createElement('div');
        const status = document.createElement('p');
        status.textContent = (strings.ticketStatusLabel || 'Status') + ': ' + (data.status || '');
        container.appendChild(status);
        const updated = document.createElement('p');
        updated.textContent = (strings.ticketUpdatedLabel || 'Updated') + ': ' + (data.updated || '');
        container.appendChild(updated);
        const messagesWrap = document.createElement('div');
        messagesWrap.className = 'pax-ticket-thread';
        if (Array.isArray(data.messages) && data.messages.length) {
          data.messages.forEach(function (msg) {
            const row = document.createElement('div');
            row.className = 'pax-ticket-message ' + (msg.sender === 'agent' ? 'agent' : 'user');
            const meta = document.createElement('div');
            meta.className = 'meta';
            meta.textContent = msg.sender.toUpperCase() + ' · ' + (msg.created || '');
            row.appendChild(meta);
            const body = document.createElement('div');
            body.className = 'body';
            body.innerHTML = msg.note || '';
            row.appendChild(body);
            
            // Add attachments if present
            if (Array.isArray(msg.attachments) && msg.attachments.length) {
              const attachmentsWrap = document.createElement('div');
              attachmentsWrap.className = 'pax-message-attachments';
              msg.attachments.forEach(function (att) {
                if (att.is_image) {
                  const img = document.createElement('img');
                  img.src = att.url;
                  img.alt = att.file_name;
                  img.className = 'pax-attachment-image';
                  img.title = att.file_name;
                  img.addEventListener('click', function () {
                    window.open(att.url, '_blank');
                  });
                  attachmentsWrap.appendChild(img);
                } else {
                  const link = document.createElement('a');
                  link.href = att.url;
                  link.className = 'pax-attachment-link';
                  link.target = '_blank';
                  link.rel = 'noopener noreferrer';
                  link.innerHTML = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11z"/></svg>' +
                    '<div class="file-info">' +
                    '<span class="file-name">' + (att.file_name || 'Download') + '</span>' +
                    '<span class="file-size">' + formatFileSize(parseInt(att.file_size || 0)) + '</span>' +
                    '</div>';
                  attachmentsWrap.appendChild(link);
                }
              });
              row.appendChild(attachmentsWrap);
            }
            
            messagesWrap.appendChild(row);
          });
        } else {
          const empty = document.createElement('p');
          empty.textContent = strings.ticketNoMessages || 'No messages yet.';
          messagesWrap.appendChild(empty);
        }
        container.appendChild(messagesWrap);
        ticketDetailModal.setContent(container);
      } else {
        const fallback = (data && data.message) ? data.message : (strings.ticketViewError || 'Unable to load ticket details.');
        ticketDetailModal.setContent(fallback);
      }
    } catch (error) {
      ticketDetailModal.setContent(strings.ticketViewError || 'Unable to load ticket details.');
    }
  }

  async function deleteTicket(id) {
    if (!rest.ticket) {
      return;
    }
    try {
      const response = await restFetch(rest.ticket + '/' + id, { method: 'DELETE' });
      const data = await response.json().catch(function () {
        return {};
      });
      if (response.ok && data && data.ok) {
        showToast(strings.ticketDeleteSuccess || 'Ticket deleted successfully.');
        ticketDetailModal.close();
        updateCooldown();
        loadTicketList();
      } else {
        showToast((strings.ticketDeleteError || 'Unable to delete ticket.') + (data && data.message ? ' ' + data.message : ''));
      }
    } catch (error) {
      showToast(strings.ticketDeleteError || 'Unable to delete ticket.');
    }
  }

  async function openMyRequestModal() {
    myRequestModal.open();
    myRequestModal.setLoading();
    const items = await fetchTicketList();
    renderTicketList(items);
  }

  const feedbackModal = buildModal(
    'pax-feedback-modal',
    strings.feedbackTitle || 'Send Feedback',
    [
      { tag: 'textarea', id: 'pax-feedback-message', rows: 4, placeholder: strings.feedbackPlaceholder || 'Share your feedback or suggestions *' },
    ],
    strings.feedbackSubmit || 'Send Feedback'
  );

  feedbackModal.submit.addEventListener('click', async function () {
    const values = feedbackModal.getValues();
    if (!values['pax-feedback-message']) {
      showToast(strings.fillAll || 'Fill all fields.');
      return;
    }
    if (!rest.feedback) {
      showToast(strings.networkError || 'Network error.');
      return;
    }
    feedbackModal.setLoading(strings.sending || 'Sending…');
    try {
      const response = await restFetch(rest.feedback, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          message: values['pax-feedback-message'],
        }),
      });
      const data = await response.json().catch(function () {
        return {};
      });
      feedbackModal.resetSubmit();
      if (response.ok && data && data.ok) {
        feedbackModal.close();
        const field = feedbackModal.body.querySelector('#pax-feedback-message');
        if (field) {
          field.value = '';
        }
        showToast(strings.feedbackThanks || 'Thank you for your feedback!');
      } else {
        showToast((strings.feedbackError || 'Unable to send feedback.') + (data && data.message ? ' ' + data.message : ''));
      }
    } catch (error) {
      feedbackModal.resetSubmit();
      showToast(strings.networkError || 'Network error.');
    }
  });

  const donateModal = buildInfoModal('pax-donate-modal', strings.donateTitle || 'Support the Developer');

  function openDonateModal() {
    donateModal.setContent((function () {
      const wrap = document.createElement('div');
      const paragraph = document.createElement('p');
      paragraph.textContent = strings.donateDescription || 'Thank you for considering a donation. Your support keeps the project alive!';
      wrap.appendChild(paragraph);
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'btn primary';
      button.textContent = strings.donateButton || 'Open Donation Page';
      button.addEventListener('click', async function () {
        const url = links.donate || 'https://www.paypal.me/AhmadAlkhalaf29';
        window.open(url, '_blank', 'noopener,noreferrer');
        showToast(strings.donateThanks || 'Donation link opened in a new tab.');
        if (rest.donate) {
          try {
            await restFetch(rest.donate, { method: 'POST' });
          } catch (err) {
            // ignore logging errors
          }
        }
        donateModal.close();
      });
      wrap.appendChild(button);
      return wrap;
    })());
    donateModal.open();
  }

  if (menu) {
    menu.addEventListener('click', function (event) {
      const item = safeClosest(event.target, '.pax-item');
      if (!item) {
        return;
      }
      const action = item.dataset.act;
      if (typeof closeMenuWithAnimation === 'function') {
        closeMenuWithAnimation();
      } else {
        menu.classList.remove('open');
      }
      if (action === 'chat') {
        // Check chat access control
        const reason = shouldBlockChat();
        if (reason === 'disabled') {
          showToast(strings.chatDisabled || 'Chat is currently disabled. Please try again later.');
          return;
        }
        if (reason === 'login_required') {
          showToast(strings.loginRequired || 'Please log in to use the chat system.');
          return;
        }
        chat.classList.add('open');
        focusInput();
      }
      if (action === 'ticket') {
        if (!isLoggedIn) {
          showToast(strings.loginRequired || 'Please log in to create a support ticket.');
          return;
        }
        openTicketModal();
      }
      if (action === 'help') {
        openKnowledgeModal();
      }
      if (action === 'agent') {
        agentModal.open();
      }
      if (action === 'livechat') {
        // Trigger Live Chat system via menu integration
        if (window.paxLiveChat && window.paxLiveChat.enabled) {
          // Call the exposed public method
          if (typeof window.paxLiveChatOpen === 'function') {
            window.paxLiveChatOpen();
          } else {
            showToast('Live Chat is loading...');
          }
        } else {
          showToast('Live Chat is not available at the moment.');
        }
      }
      if (action === 'callback') {
        if (!isLoggedIn) {
          showToast(strings.loginRequired || 'Please log in to request a callback.');
          return;
        }
        openScheduleModal();
      }
      if (action === 'troubleshooter') {
        troubleModal.open();
      }
      if (action === 'diag') {
        openDiagnosticsModal();
      }
      if (action === 'order') {
        const emailField = orderModal.body.querySelector('#pax-order-email');
        if (emailField && user.email && !emailField.value) {
          emailField.value = user.email;
        }
        orderModal.open();
      }
      if (action === 'myreq') {
        if (!isLoggedIn) {
          showToast(strings.loginRequired || 'Please log in to view your requests.');
          return;
        }
        openMyRequestModal();
      }
      if (action === 'feedback') {
        feedbackModal.open();
      }
      if (action === 'donate') {
        openDonateModal();
      }
      if (action === 'whatsnew') {
        if (links.whatsNew) {
          window.open(links.whatsNew, '_blank');
        } else {
          showToast(strings.comingSoon || 'Coming soon!');
        }
      }
    });
  }

  window.openTicket = openTicketModal;

  // File attachment handling
  const attachBtn = document.getElementById('pax-attach');
  const fileInput = document.getElementById('pax-file-input');
  const attachmentPreview = document.getElementById('pax-attachment-preview');
  const dropZone = document.getElementById('pax-drop-zone');
  let selectedFiles = [];

  if (attachBtn && fileInput) {
    attachBtn.addEventListener('click', function () {
      fileInput.click();
    });

    fileInput.addEventListener('change', function (e) {
      handleFileSelect(e.target.files);
    });
  }

  // Drag and drop functionality
  if (chat && dropZone) {
    let dragCounter = 0;

    chat.addEventListener('dragenter', function (e) {
      e.preventDefault();
      e.stopPropagation();
      dragCounter++;
      if (dragCounter === 1) {
        dropZone.style.display = 'flex';
        requestAnimationFrame(function () {
          dropZone.classList.add('active');
        });
      }
    });

    chat.addEventListener('dragleave', function (e) {
      e.preventDefault();
      e.stopPropagation();
      dragCounter--;
      if (dragCounter === 0) {
        dropZone.classList.remove('active');
        setTimeout(function () {
          if (!dropZone.classList.contains('active')) {
            dropZone.style.display = 'none';
          }
        }, 200);
      }
    });

    chat.addEventListener('dragover', function (e) {
      e.preventDefault();
      e.stopPropagation();
    });

    chat.addEventListener('drop', function (e) {
      e.preventDefault();
      e.stopPropagation();
      dragCounter = 0;
      dropZone.classList.remove('active');
      setTimeout(function () {
        dropZone.style.display = 'none';
      }, 200);

      if (e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files.length > 0) {
        handleFileSelect(e.dataTransfer.files);
      }
    });
  }

  function handleFileSelect(files) {
    if (!files || files.length === 0) {
      return;
    }

    const maxSize = 5 * 1024 * 1024; // 5MB
    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                          'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                          'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                          'text/plain', 'application/zip'];

    for (let i = 0; i < files.length; i++) {
      const file = files[i];

      if (file.size > maxSize) {
        showToast('File "' + file.name + '" is too large. Maximum size is 5MB.');
        continue;
      }

      if (!allowedTypes.includes(file.type)) {
        showToast('File type not allowed for "' + file.name + '".');
        continue;
      }

      // Check if file already selected
      const exists = selectedFiles.some(function (f) {
        return f.name === file.name && f.size === file.size;
      });

      if (!exists) {
        selectedFiles.push(file);
      }
    }

    updateAttachmentPreview();
    if (fileInput) {
      fileInput.value = '';
    }
  }

  function updateAttachmentPreview() {
    if (!attachmentPreview) {
      return;
    }

    if (selectedFiles.length === 0) {
      attachmentPreview.style.display = 'none';
      attachmentPreview.innerHTML = '';
      return;
    }

    attachmentPreview.style.display = 'block';
    attachmentPreview.innerHTML = '';

    selectedFiles.forEach(function (file, index) {
      const item = document.createElement('div');
      item.className = 'pax-attachment-item';
      
      const name = document.createElement('span');
      name.className = 'pax-attachment-name';
      name.textContent = file.name;
      item.appendChild(name);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'pax-attachment-remove';
      removeBtn.textContent = '×';
      removeBtn.addEventListener('click', function () {
        selectedFiles.splice(index, 1);
        updateAttachmentPreview();
      });
      item.appendChild(removeBtn);

      attachmentPreview.appendChild(item);
    });
  }

  function getSelectedFiles() {
    return selectedFiles;
  }

  function clearSelectedFiles() {
    selectedFiles = [];
    updateAttachmentPreview();
  }

  window.paxGetSelectedFiles = getSelectedFiles;
  window.paxClearSelectedFiles = clearSelectedFiles;

  // v5.4.9: Connect unified chat menu events to existing modals
  console.log('PAX-ASSETS: Registering event listeners for unified chat menu');
  console.log('PAX-ASSETS: Available functions:', {
    openTicketModal: typeof openTicketModal,
    troubleModal: typeof troubleModal,
    openDiagnosticsModal: typeof openDiagnosticsModal,
    openScheduleModal: typeof openScheduleModal,
    orderModal: typeof orderModal,
    openMyRequestModal: typeof openMyRequestModal,
    feedbackModal: typeof feedbackModal,
    setSpeed: typeof setSpeed
  });
  
  document.addEventListener('pax-open-ticket-modal', function() {
    console.log('PAX-ASSETS: Received pax-open-ticket-modal event');
    console.log('PAX-ASSETS: openTicketModal type:', typeof openTicketModal);
    if (typeof openTicketModal === 'function') {
      console.log('PAX-ASSETS: Calling openTicketModal()');
      openTicketModal();
      console.log('PAX-ASSETS: openTicketModal() called successfully');
    } else {
      console.warn('PAX-ASSETS: openTicketModal function not found');
    }
  });

  document.addEventListener('pax-open-troubleshooter', function() {
    console.log('PAX-ASSETS: Received pax-open-troubleshooter event');
    console.log('PAX-ASSETS: troubleModal type:', typeof troubleModal);
    if (typeof troubleModal !== 'undefined' && troubleModal && troubleModal.open) {
      console.log('PAX-ASSETS: Calling troubleModal.open()');
      troubleModal.open();
      console.log('PAX-ASSETS: troubleModal.open() called successfully');
    } else {
      console.warn('PAX-ASSETS: troubleModal not found or no open method');
    }
  });

  document.addEventListener('pax-open-diagnostics', function() {
    console.log('PAX-ASSETS: Received pax-open-diagnostics event');
    console.log('PAX-ASSETS: openDiagnosticsModal type:', typeof openDiagnosticsModal);
    if (typeof openDiagnosticsModal === 'function') {
      console.log('PAX-ASSETS: Calling openDiagnosticsModal()');
      openDiagnosticsModal();
      console.log('PAX-ASSETS: openDiagnosticsModal() called successfully');
    } else {
      console.warn('PAX-ASSETS: openDiagnosticsModal function not found');
    }
  });

  document.addEventListener('pax-open-schedule-modal', function() {
    console.log('PAX-ASSETS: Received pax-open-schedule-modal event');
    console.log('PAX-ASSETS: openScheduleModal type:', typeof openScheduleModal);
    if (typeof openScheduleModal === 'function') {
      console.log('PAX-ASSETS: Calling openScheduleModal()');
      openScheduleModal();
      console.log('PAX-ASSETS: openScheduleModal() called successfully');
    } else {
      console.warn('PAX-ASSETS: openScheduleModal function not found');
    }
  });

  document.addEventListener('pax-open-order-modal', function() {
    console.log('PAX-ASSETS: Received pax-open-order-modal event');
    console.log('PAX-ASSETS: orderModal type:', typeof orderModal);
    if (typeof orderModal !== 'undefined' && orderModal && orderModal.open) {
      console.log('PAX-ASSETS: Pre-filling email and calling orderModal.open()');
      const emailField = orderModal.body.querySelector('#pax-order-email');
      if (emailField && user.email && !emailField.value) {
        emailField.value = user.email;
      }
      orderModal.open();
      console.log('PAX-ASSETS: orderModal.open() called successfully');
    } else {
      console.warn('PAX-ASSETS: orderModal not found or no open method');
    }
  });

  document.addEventListener('pax-open-myrequest', function() {
    console.log('PAX-ASSETS: Received pax-open-myrequest event');
    console.log('PAX-ASSETS: openMyRequestModal type:', typeof openMyRequestModal);
    if (typeof openMyRequestModal === 'function') {
      console.log('PAX-ASSETS: Calling openMyRequestModal()');
      openMyRequestModal();
      console.log('PAX-ASSETS: openMyRequestModal() called successfully');
    } else {
      console.warn('PAX-ASSETS: openMyRequestModal function not found');
    }
  });

  document.addEventListener('pax-open-feedback', function() {
    console.log('PAX-ASSETS: Received pax-open-feedback event');
    console.log('PAX-ASSETS: feedbackModal type:', typeof feedbackModal);
    if (typeof feedbackModal !== 'undefined' && feedbackModal && feedbackModal.open) {
      console.log('PAX-ASSETS: Calling feedbackModal.open()');
      feedbackModal.open();
      console.log('PAX-ASSETS: feedbackModal.open() called successfully');
    } else {
      console.warn('PAX-ASSETS: feedbackModal not found or no open method');
    }
  });

  console.log('PAX-ASSETS: All event listeners registered successfully');

  // v5.4.9: Expose functions for debugging and direct fallback calls
  console.log('PAX-ASSETS: Initializing window.paxDebug object');
  window.paxDebug = {
    openTicketModal: typeof openTicketModal !== 'undefined' ? openTicketModal : null,
    troubleModal: typeof troubleModal !== 'undefined' ? troubleModal : null,
    openDiagnosticsModal: typeof openDiagnosticsModal !== 'undefined' ? openDiagnosticsModal : null,
    openScheduleModal: typeof openScheduleModal !== 'undefined' ? openScheduleModal : null,
    orderModal: typeof orderModal !== 'undefined' ? orderModal : null,
    openMyRequestModal: typeof openMyRequestModal !== 'undefined' ? openMyRequestModal : null,
    feedbackModal: typeof feedbackModal !== 'undefined' ? feedbackModal : null
  };
  console.log('PAX-ASSETS: window.paxDebug initialized:', {
    openTicketModal: typeof window.paxDebug.openTicketModal,
    troubleModal: typeof window.paxDebug.troubleModal,
    openDiagnosticsModal: typeof window.paxDebug.openDiagnosticsModal,
    openScheduleModal: typeof window.paxDebug.openScheduleModal,
    orderModal: typeof window.paxDebug.orderModal,
    openMyRequestModal: typeof window.paxDebug.openMyRequestModal,
    feedbackModal: typeof window.paxDebug.feedbackModal
  });
})();
