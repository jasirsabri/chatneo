(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		trackPageVisit();

		var root       = document.getElementById('wcw-widget-root');
		var bubbleBtn  = document.getElementById('wcw-bubble-btn');
		var panel      = document.getElementById('wcw-chat-panel');
		var closeBtn   = document.getElementById('wcw-chat-close');
		var messagesEl = document.getElementById('wcw-chat-messages');
		var form       = document.getElementById('wcw-chat-form');
		var sendBtn    = document.getElementById('wcw-chat-send');
		var activeInput = document.getElementById('wcw-input-active');

		if (!root || !bubbleBtn || !panel || !form || !activeInput) {
			return;
		}

		var i18n = wcwData.i18n;
		var routes = wcwData.routes || [];

		// ---------- Phone input (country code dropdown) ----------
		// The single shared input is reused across every question in every
		// route, so intl-tel-input is attached/detached on demand rather
		// than living permanently on one dedicated field like earlier
		// versions of this widget.
		var iti = null;

		function attachPhoneInput() {
			if (iti || !window.intlTelInput) {
				return;
			}
			iti = window.intlTelInput(activeInput, {
				initialCountry: 'auto',
				geoIpLookup: function (callback) {
					fetch('https://ipapi.co/json')
						.then(function (res) { return res.json(); })
						.then(function (data) { callback(data.country_code || 'us'); })
						.catch(function () { callback('us'); });
				},
				utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23.8.0/build/js/utils.js',
				separateDialCode: true,
				dropdownContainer: document.body,
			});
		}

		function detachPhoneInput() {
			if (iti) {
				try { iti.destroy(); } catch (e) { /* no-op */ }
				iti = null;
			}
		}

		// ---------- Conversation state ----------
		var name = '';
		var selectedRoute = null;
		var questionIndex = -1; // -1 until a route is picked
		var answers = {};
		var submissionId = '';
		var conversationStarted = false;
		var awaitingResponse = false;
		var currentQuestion = null; // the question object currently being asked, or null (name / route step)

		function setInputEnabled(enabled) {
			sendBtn.disabled = ! enabled;
			activeInput.disabled = ! enabled;
		}

		function addMessage(text, cssClass) {
			var el = document.createElement('div');
			el.className = 'wcw-msg ' + cssClass;
			el.textContent = text;
			messagesEl.appendChild(el);
			scrollToBottom();
			return el;
		}

		function scrollToBottom() {
			messagesEl.scrollTop = messagesEl.scrollHeight;
		}

		function addTypingIndicator() {
			var el = document.createElement('div');
			el.className = 'wcw-typing';
			el.innerHTML = '<span></span><span></span><span></span>';
			messagesEl.appendChild(el);
			scrollToBottom();
			return el;
		}

		function typingDelay(text) {
			var base = 800;
			var perChar = 16;
			var jitter = Math.random() * 500;
			var computed = base + Math.min(text.length * perChar, 1600) + jitter;
			return Math.max(900, Math.min(computed, 2600));
		}

		function botSay(text, delay) {
			return new Promise(function (resolve) {
				awaitingResponse = true;
				setInputEnabled(false);
				var typing = addTypingIndicator();
				window.setTimeout(function () {
					typing.remove();
					addMessage(text, 'bot');
					awaitingResponse = false;
					setInputEnabled(true);
					resolve();
				}, delay || typingDelay(text));
			});
		}

		function fillTemplate(str, vars) {
			return str.replace(/\{(\w+)\}/g, function (_, key) {
				return vars[key] !== undefined ? vars[key] : '';
			});
		}

		// ---------- Page-visit tracking ----------
		function getVisitedPagesJSON() {
			try {
				return sessionStorage.getItem('wcw_visited_pages') || '[]';
			} catch (e) {
				return '[]';
			}
		}

		function trackPageVisit() {
			try {
				var raw = sessionStorage.getItem('wcw_visited_pages');
				var pages = raw ? JSON.parse(raw) : [];
				var current = window.location.href;
				if (pages[pages.length - 1] !== current) {
					pages.push(current);
				}
				if (pages.length > 20) {
					pages = pages.slice(pages.length - 20);
				}
				sessionStorage.setItem('wcw_visited_pages', JSON.stringify(pages));
			} catch (e) {
				// sessionStorage can be unavailable — page tracking is a
				// nice-to-have, fail silently.
			}
		}

		// ---------- Input step handling ----------
		function showInputForQuestion(question) {
			currentQuestion = question;

			if (question.type === 'phone') {
				activeInput.type = 'tel';
				attachPhoneInput();
			} else {
				detachPhoneInput();
				activeInput.type = (question.type === 'email') ? 'email' : 'text';
			}

			activeInput.placeholder = i18n.placeholderText;
			activeInput.value = '';
			window.setTimeout(function () { activeInput.focus(); }, 50);
		}

		function hideInputRow() {
			form.classList.add('wcw-hidden');
		}

		function showInputRow() {
			form.classList.remove('wcw-hidden');
		}

		// ---------- Conversation flow ----------
		function startConversation() {
			if (conversationStarted) {
				return;
			}
			conversationStarted = true;

			var lines = (wcwData.greetingLines && wcwData.greetingLines.length) ? wcwData.greetingLines.slice() : [];

			function playNextLine() {
				var line = lines.shift();
				if (line === undefined) {
					showInputForQuestion({ key: 'name', type: 'text' });
					return;
				}
				botSay(line).then(playNextLine);
			}

			playNextLine();
		}

		function isValidEmail(email) {
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
		}

		function validatePhoneValue() {
			if (iti) {
				if (activeInput.value.trim() && iti.isValidNumber()) {
					return { valid: true, value: iti.getNumber() };
				}
				return { valid: false, value: '' };
			}
			var raw = activeInput.value.trim().replace(/[^\d+]/g, '');
			if (/^\+?[1-9]\d{7,14}$/.test(raw)) {
				return { valid: true, value: raw };
			}
			return { valid: false, value: '' };
		}

		function askRouteChoice() {
			botSay(fillTemplate(i18n.askRoute, { name: name })).then(function () {
				showRouteButtons();
			});
		}

		function showRouteButtons() {
			hideInputRow();

			var group = document.createElement('div');
			group.className = 'wcw-department-buttons';

			routes.forEach(function (route) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'wcw-department-btn';
				btn.textContent = route.label;
				btn.addEventListener('click', function () {
					handleRouteChoice(route, group);
				});
				group.appendChild(btn);
			});

			messagesEl.appendChild(group);
			scrollToBottom();
		}

		function handleRouteChoice(route, buttonGroupEl) {
			// Deferred to the next tick: removing this synchronously
			// detaches it from the DOM before the same click event finishes
			// bubbling to the document-level "click outside closes the
			// panel" listener, which would otherwise wrongly treat it as an
			// outside click.
			if (buttonGroupEl && buttonGroupEl.parentNode) {
				window.setTimeout(function () { buttonGroupEl.remove(); }, 0);
			}

			addMessage(route.label, 'user');
			selectedRoute = route;
			questionIndex = -1;
			showInputRow();
			advanceQuestion();
		}

		function advanceQuestion() {
			questionIndex += 1;

			if (!selectedRoute || questionIndex >= selectedRoute.questions.length) {
				finishConversation();
				return;
			}

			var question = selectedRoute.questions[questionIndex];
			botSay(question.prompt).then(function () {
				showInputForQuestion(question);
			});
		}

		function finishConversation() {
			hideInputRow();
			detachPhoneInput();
			var typing = addTypingIndicator();

			syncSubmission(true)
				.then(function (json) {
					typing.remove();
					if (json && json.success && json.data) {
						if (json.data.submission_id) {
							submissionId = json.data.submission_id;
						}
						if (json.data.redirect_url) {
							botSay(fillTemplate(i18n.whatsappTransition, { name: name })).then(function () {
								addWhatsAppButton(json.data.redirect_url);
							});
							return;
						}
						if (json.data.direct_message) {
							addMessage(json.data.direct_message, 'bot');
							return;
						}
					}
					showSubmissionError();
				})
				.catch(function () {
					typing.remove();
					showSubmissionError();
				});
		}

		// Sends the current name/route/answers to the server. Used both for
		// partial saves after each question (fire-and-forget) and as the
		// final call once every question has been answered.
		function syncSubmission(isFinal) {
			var formData = new FormData();
			formData.append('action', 'wcw_submit_route');
			formData.append('nonce', wcwData.nonce);
			formData.append('submission_id', submissionId);
			formData.append('route_id', selectedRoute ? selectedRoute.id : '');
			formData.append('name', name);
			formData.append('answers', JSON.stringify(answers));
			formData.append('is_final', isFinal ? '1' : '0');
			formData.append('page_url', window.location.href);
			formData.append('visited_pages', getVisitedPagesJSON());

			return fetch(wcwData.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: formData,
			}).then(function (res) { return res.json(); });
		}

		function savePartialProgress() {
			syncSubmission(false)
				.then(function (json) {
					if (json && json.success && json.data && json.data.submission_id) {
						submissionId = json.data.submission_id;
					}
				})
				.catch(function () {
					// Best-effort only — a failed partial save shouldn't
					// interrupt the visitor's conversation.
				});
		}

		function showSubmissionError() {
			addMessage(i18n.genericError, 'error');
			var retryBtn = document.createElement('button');
			retryBtn.type = 'button';
			retryBtn.className = 'wcw-retry-btn';
			retryBtn.textContent = i18n.retryButton;
			retryBtn.addEventListener('click', function () {
				retryBtn.remove();
				finishConversation();
			});
			messagesEl.appendChild(retryBtn);
			scrollToBottom();
		}

		function addWhatsAppButton(url) {
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'wcw-whatsapp-cta';
			btn.innerHTML = '<svg viewBox="0 0 32 32" width="16" height="16" fill="currentColor"><path d="M16 3C9.4 3 4 8.1 4 14.4c0 2.5.9 4.8 2.4 6.7L5 29l8.2-2.1c.9.2 1.8.3 2.8.3 6.6 0 12-5.1 12-11.4S22.6 3 16 3z"/></svg><span>' + i18n.whatsappButton + '</span>';
			btn.addEventListener('click', function () {
				window.open(url, '_blank');
			});
			messagesEl.appendChild(btn);
			scrollToBottom();
		}

		// ---------- Submit handling ----------
		function handleSubmit(e) {
			e.preventDefault();

			if (awaitingResponse) {
				return;
			}

			// Step 1: the universal Name question, before any route exists.
			if (!selectedRoute) {
				var typedName = activeInput.value.trim();
				if (!typedName) {
					botSay(i18n.invalidName);
					return;
				}
				name = typedName;
				addMessage(name, 'user');

				// With only one route configured, asking "which department"
				// is pointless — go straight into its questions instead.
				if (routes.length === 1) {
					selectedRoute = routes[0];
					questionIndex = -1;
					showInputRow();
					advanceQuestion();
				} else {
					askRouteChoice();
				}
				return;
			}

			// Step 2+: whatever question this route currently asks.
			var question = currentQuestion;
			var rawValue = activeInput.value.trim();

			if (question.type === 'phone') {
				if ('' === rawValue && !question.required) {
					answers[question.key] = '';
					addMessage('—', 'user');
					savePartialProgress();
					advanceQuestion();
					return;
				}
				var phoneResult = validatePhoneValue();
				if (!phoneResult.valid) {
					botSay(i18n.invalidPhone);
					return;
				}
				answers[question.key] = phoneResult.value;
				addMessage(phoneResult.value, 'user');
				savePartialProgress();
				advanceQuestion();
				return;
			}

			if (question.type === 'email') {
				if ('' === rawValue && !question.required) {
					answers[question.key] = '';
					addMessage('—', 'user');
					savePartialProgress();
					advanceQuestion();
					return;
				}
				if (!rawValue || !isValidEmail(rawValue)) {
					botSay(i18n.invalidEmail);
					return;
				}
				answers[question.key] = rawValue;
				addMessage(rawValue, 'user');
				savePartialProgress();
				advanceQuestion();
				return;
			}

			// Plain text question.
			if ('' === rawValue) {
				if (question.required) {
					botSay(i18n.invalidRequired);
					return;
				}
				answers[question.key] = '';
				addMessage('—', 'user');
				savePartialProgress();
				advanceQuestion();
				return;
			}

			answers[question.key] = rawValue;
			addMessage(rawValue, 'user');
			savePartialProgress();
			advanceQuestion();
		}

		form.addEventListener('submit', handleSubmit);

		// ---------- Panel open/close ----------
		function openPanel() {
			panel.classList.remove('wcw-hidden');
			root.classList.add('wcw-open');
			startConversation();
			window.setTimeout(function () { activeInput.focus(); }, 200);
		}

		function closePanel() {
			panel.classList.add('wcw-hidden');
			root.classList.remove('wcw-open');
		}

		function togglePanel() {
			if (panel.classList.contains('wcw-hidden')) {
				openPanel();
			} else {
				closePanel();
			}
		}

		bubbleBtn.addEventListener('click', togglePanel);
		closeBtn.addEventListener('click', closePanel);

		document.addEventListener('click', function (e) {
			// The phone country dropdown renders in <body>, outside our
			// widget root — don't treat clicks inside it as "clicked
			// outside the chat".
			if (e.target.closest && e.target.closest('[class*="iti"]')) {
				return;
			}
			if (!root.contains(e.target) && !panel.classList.contains('wcw-hidden')) {
				closePanel();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape' && !panel.classList.contains('wcw-hidden')) {
				closePanel();
			}
		});

		showInputRow();
	});
})();
