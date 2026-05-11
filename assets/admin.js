(function () {

            var FIELDS = wcdcfData.fields;
            var formCard = document.getElementById('wcdcf-form-card');
            var placeholder = document.getElementById('wcdcf-form-placeholder');
            var formTitle = document.getElementById('wcdcf-form-title');
            var submitBtn = document.getElementById('wcdcf-submit-btn');
            var editingLbl = document.getElementById('wcdcf-editing-lbl');
            var editIdInput = document.getElementById('wcdcf-edit-id');
            var optionsRow = document.getElementById('wcdcf-options-row');
            var condValRow = document.getElementById('wcdcf-cond-val-row');
            var condValLabel = document.getElementById('wcdcf-cond-val-label');
            var condHint = document.getElementById('wcdcf-cond-hint');
            var regexRow = document.getElementById('wcdcf-regex-row');
            var typeSelect = document.getElementById('field_type');
            var condSelect = document.getElementById('condition_type');
            var regexRow = document.getElementById('wcdcf-regex-row');

            // ── Show / hide form ──────────────────────────────────────────
            function showForm() {
                placeholder.style.display = 'none';
                formCard.style.display = 'block';
            }
            function hideForm() {
                placeholder.style.display = '';
                formCard.style.display = 'none';
                clearActiveCard();
            }

            // ── Field type → options textarea ─────────────────────────────
            function syncTypeFields() {
                var type = typeSelect.value;
                optionsRow.style.display = type === 'select' ? '' : 'none';
                regexRow.style.display = (type === 'checkbox') ? 'none' : '';
            }
            typeSelect.addEventListener('change', syncTypeFields);

            // ── Condition type → value input ──────────────────────────────
            var condMeta = {
                always: { hide: true, label: '', hint: '' },
                cart_total_gt: {
                    hide: false, label: wcdcfData.i18n.amount,
                    hint: wcdcfData.i18n.amount_hint
                },
                shipping_country: {
                    hide: false, label: wcdcfData.i18n.country,
                    hint: wcdcfData.i18n.country_hint
                },
                product_in_cart: {
                    hide: false, label: wcdcfData.i18n.product,
                    hint: wcdcfData.i18n.product_hint
                },
            };

            function syncCondFields() {
                var meta = condMeta[condSelect.value] || { hide: true, label: '', hint: '' };
                condValRow.style.display = meta.hide ? 'none' : '';
                condValLabel.textContent = meta.label;
                condHint.textContent = meta.hint;
            }
            condSelect.addEventListener('change', syncCondFields);

            // ── New field button ──────────────────────────────────────────
            document.getElementById('wcdcf-new-btn').addEventListener('click', function () {
                resetForm();
                showForm();
            });

            // ── Cancel button ─────────────────────────────────────────────
            document.getElementById('wcdcf-cancel-btn').addEventListener('click', function () {
                resetForm();
                hideForm();
            });

            // ── Edit field ────────────────────────────────────────────────
            window.wcdcfEditField = function (id) {
                var field = FIELDS.find(function (f) { return f.id === id; });
                if (!field) return;

                resetForm();
                editIdInput.value = field.id;

                document.getElementById('field_id').value = field.id;
                document.getElementById('field_label').value = field.label;
                document.getElementById('field_type').value = field.type;
                document.getElementById('field_required').checked = !!field.required;
                document.getElementById('field_options').value = field.options || '';
                document.getElementById('condition_type').value = field.condition_type;
                document.getElementById('condition_value').value = field.condition_value || '';
                document.getElementById('validation_regex').value = field.validation_regex || '';

                syncTypeFields();
                syncCondFields();

                formTitle.textContent = '✏️ ' + wcdcfData.i18n.edit_field + ': ' + field.label;
                submitBtn.textContent = '💾 ' + wcdcfData.i18n.update_field;
                editingLbl.style.display = 'inline';

                setActiveCard(id);
                showForm();
            };

            // ── Delete field ──────────────────────────────────────────────
            window.wcdcfDeleteField = function (id, label) {
                if (!confirm(wcdcfData.i18n.delete_field + ' "' + label + '"?')) return;
                document.getElementById('wcdcf-del-id').value = id;
                document.getElementById('wcdcf-delete-form').submit();
            };

            // ── Active card highlight ─────────────────────────────────────
            function setActiveCard(id) {
                document.querySelectorAll('.wcdcf-field-card').forEach(function (c) {
                    c.classList.toggle('active', c.dataset.id === id);
                });
            }
            function clearActiveCard() {
                document.querySelectorAll('.wcdcf-field-card').forEach(function (c) {
                    c.classList.remove('active');
                });
            }

            // ── Reset form to create mode ─────────────────────────────────
            function resetForm() {
                var form = formCard.querySelector('form');
                if (form) form.reset();
                editIdInput.value = '';
                formTitle.textContent = '➕ ' + wcdcfData.i18n.new_field;
                submitBtn.textContent = '💾 ' + wcdcfData.i18n.save_field;
                editingLbl.style.display = 'none';
                clearActiveCard();
                syncTypeFields();
                syncCondFields();
            }

            // Run on page load
            syncTypeFields();
            syncCondFields();

        }());