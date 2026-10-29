/**
 * Wycan Freemius Stats, settings screen: product cards (add, edit, remove), connection test,
 * default product badge, copy buttons. No dependency.
 */
(function () {
	"use strict";

	const config = window.WFS_ADMIN || {};
	const i18n = config.i18n || {};

	function text(key, fallback) {
		return i18n[key] || fallback;
	}

	function initProducts() {
		const form = document.querySelector("[data-wfs-form]");
		const list = document.querySelector("[data-wfs-products]");
		const template = document.querySelector("[data-wfs-template]");
		const add = document.querySelector("[data-wfs-add]");
		const empty = document.querySelector("[data-wfs-empty]");
		const unsaved = document.querySelector("[data-wfs-unsaved]");
		if (!form || !list || !template || !add) {
			return;
		}

		let nextIndex = parseInt(add.dataset.nextIndex, 10) || 0;

		function markUnsaved() {
			if (unsaved) {
				unsaved.hidden = false;
			}
		}

		function refreshDefaultBadge() {
			const cards = list.querySelectorAll("[data-wfs-product]");
			cards.forEach(function (card, position) {
				const badge = card.querySelector("[data-wfs-default-badge]");
				if (badge) {
					badge.hidden = position !== 0;
				}
			});
			if (empty) {
				empty.hidden = cards.length > 0;
			}
		}

		function setOpen(card, open) {
			const toggle = card.querySelector("[data-wfs-toggle]");
			const body = card.querySelector(".wfs-product-body");
			card.classList.toggle("is-open", open);
			if (body) {
				body.hidden = !open;
			}
			if (toggle) {
				toggle.setAttribute("aria-expanded", open ? "true" : "false");
				toggle.textContent = open ? text("close", "Close") : text("edit", "Edit");
			}
		}

		function syncLabels(card) {
			const nameField = card.querySelector('[data-wfs-field="name"]');
			const slugField = card.querySelector('[data-wfs-field="slug"]');
			const idField = card.querySelector('[data-wfs-field="product_id"]');
			const nameLabel = card.querySelector("[data-wfs-name-label]");
			const slugLabel = card.querySelector("[data-wfs-slug-label]");
			const idWrap = card.querySelector("[data-wfs-id-wrap]");
			const idLabel = card.querySelector("[data-wfs-id-label]");
			const name = nameField ? nameField.value.trim() : "";
			const slug = slugField ? slugField.value.trim() : "";
			const id = idField ? idField.value.trim() : "";
			if (nameLabel) {
				nameLabel.textContent = name || slug || text("newProduct", "New product");
			}
			if (slugLabel) {
				slugLabel.textContent = slug;
				slugLabel.hidden = slug === "";
			}
			if (idLabel && idWrap) {
				idLabel.textContent = id;
				idWrap.hidden = id === "";
			}
		}

		add.addEventListener("click", function () {
			const html = template.innerHTML.replace(/__INDEX__/g, String(nextIndex++));
			list.insertAdjacentHTML("beforeend", html);
			const card = list.lastElementChild;
			refreshDefaultBadge();
			markUnsaved();
			const first = card.querySelector('[data-wfs-field="product_id"]');
			if (first) {
				first.focus();
			}
		});

		list.addEventListener("click", function (event) {
			const card = event.target.closest("[data-wfs-product]");
			if (!card) {
				return;
			}
			if (event.target.closest("[data-wfs-toggle]")) {
				setOpen(card, !card.classList.contains("is-open"));
			} else if (event.target.closest("[data-wfs-remove]")) {
				const isDefault = card === list.firstElementChild && list.children.length > 1;
				const message = isDefault ? text("confirmDefault", "Remove the default product?") : text("confirmRemove", "Remove this product?");
				if (window.confirm(message)) {
					card.remove();
					refreshDefaultBadge();
					markUnsaved();
				}
			} else if (event.target.closest("[data-wfs-test]")) {
				testConnection(card);
			}
		});

		list.addEventListener("input", function (event) {
			const card = event.target.closest("[data-wfs-product]");
			if (card) {
				syncLabels(card);
				markUnsaved();
			}
		});

		list.addEventListener("change", markUnsaved);
		form.addEventListener("submit", function () {
			if (unsaved) {
				unsaved.hidden = true;
			}
		});

		refreshDefaultBadge();
	}

	function show(output, state, message) {
		output.textContent = message;
		output.className = "wfs-test-result is-" + state;
	}

	async function testConnection(card) {
		const button = card.querySelector("[data-wfs-test]");
		const output = card.querySelector("[data-wfs-test-result]");
		const idField = card.querySelector('[data-wfs-field="product_id"]');
		const tokenField = card.querySelector('[data-wfs-field="api_token"]');
		const storedSlug = card.querySelector('input[name$="[stored_slug]"]');
		if (!button || !output) {
			return;
		}

		const body = card.querySelector(".wfs-product-body");
		if (body && body.hidden) {
			card.querySelector("[data-wfs-toggle]").click();
		}

		const data = new FormData();
		data.append("action", config.action || "wfs_test_connection");
		data.append("_ajax_nonce", config.nonce || "");
		data.append("product_id", idField ? idField.value.trim() : "");
		data.append("token", tokenField ? tokenField.value : "");
		data.append("stored_slug", storedSlug ? storedSlug.value : "");

		button.disabled = true;
		show(output, "pending", text("testing", "Testing…"));

		try {
			const response = await fetch(config.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: data,
			});
			const payload = await response.json().catch(function () {
				return {};
			});

			if (response.ok && payload.success) {
				const result = payload.data || {};
				fillIfEmpty(card, "name", result.name);
				fillIfEmpty(card, "slug", result.slug);
				const summary = text("resultSummary", "%1$s active installations, %2$s in total.")
					.replace("%1$s", formatNumber(result.active))
					.replace("%2$s", formatNumber(result.total));
				show(output, "ok", (result.name ? result.name + ": " : "") + summary);
			} else {
				const message = payload.data && payload.data.message ? payload.data.message : response.statusText;
				show(output, "error", message || text("networkError", "The request could not be sent."));
			}
		} catch (error) {
			show(output, "error", text("networkError", "The request could not be sent."));
		} finally {
			button.disabled = false;
		}
	}

	function fillIfEmpty(card, field, value) {
		const input = card.querySelector('[data-wfs-field="' + field + '"]');
		if (input && value && input.value.trim() === "") {
			input.value = value;
			input.dispatchEvent(new Event("input", { bubbles: true }));
		}
	}

	function formatNumber(value) {
		const number = Number(value);
		return Number.isFinite(number) ? number.toLocaleString(document.documentElement.lang || undefined) : String(value);
	}

	function initCopy() {
		document.addEventListener("click", async function (event) {
			const button = event.target.closest("[data-wfs-copy]");
			if (!button) {
				return;
			}
			const label = button.textContent;
			try {
				await navigator.clipboard.writeText(button.dataset.wfsCopy);
				button.textContent = text("copied", "Copied");
			} catch (error) {
				const code = button.parentElement.querySelector("code");
				if (code && window.getSelection) {
					const range = document.createRange();
					range.selectNodeContents(code);
					const selection = window.getSelection();
					selection.removeAllRanges();
					selection.addRange(range);
				}
			}
			window.setTimeout(function () {
				button.textContent = label;
			}, 1500);
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		initProducts();
		initCopy();
	});
})();
