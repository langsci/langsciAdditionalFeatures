(function () {
	const {labels, urls, form: formConfig} = window.langsciReviewsConfig;
	const {h, ref, onMounted, inject} = pkp.modules.vue;
	const ListPanel = pkp.registry.getComponent('PkpListPanel');
	const Header = pkp.registry.getComponent('PkpHeader');
	const Button = pkp.registry.getComponent('PkpButton');
	const Notification = pkp.registry.getComponent('PkpNotification');
	const PkpForm = pkp.registry.getComponent('PkpForm');
	const SideModalBody = pkp.registry.getComponent('PkpSideModalBody');
	const SideModalLayout = pkp.registry.getComponent('PkpSideModalLayoutBasic');

	const ReviewFormModal = {
		props: {
			review: {type: Object, default: null},
			saveReview: {type: Function, required: true},
		},
		setup(props) {
			const closeModal = inject('closeModal');
			const saving = ref(false);
			const error = ref('');
			inject('registerCloseCallback')(() => !saving.value);
			const initialForm = {
				...formConfig,
				fields: formConfig.fields.map((field) => ({
					...field,
					value: props.review?.[field.name] ?? '',
				})),
				hiddenFields: {reviewId: props.review?.review_id || ''},
				errors: {},
			};
			const {form, set} = pkp.modules.useForm.useForm(initialForm, {customSubmit: async (fields) => {
				saving.value = true;
				error.value = '';
				try {
					await props.saveReview(fields);
					return {data: {saved: true}, validationError: null};
				} catch (e) {
					error.value = e.message || labels.requestFailed;
					return {data: null, validationError: null};
				} finally {
					saving.value = false;
				}
			}});

			return () => h(SideModalBody, {}, {
				title: () => props.review ? labels.edit : labels.add,
				default: () => h(SideModalLayout, {}, {
					default: () => [
						error.value && h(Notification, {type: 'warning', role: 'alert'}, {default: () => error.value}),
						h(PkpForm, {...form.value, onSet: set, onSuccess: closeModal, onCancel: closeModal}),
					],
				}),
			});
		},
	};

	pkp.registry.registerComponent('LangsciReviewsEditor', {
		props: {submissionId: {type: Number, required: true}},
		setup(props) {
			const reviews = ref([]);
			const loading = ref(false);
			const loaded = ref(false);
			const failed = ref(false);
			const {openSideModal} = pkp.modules.useModal.useModal();
			const {notify} = pkp.modules.useNotify.useNotify();

			async function request(op, fields = null) {
				const url = new URL(urls[op], window.location.href);
				url.searchParams.set('submissionId', props.submissionId);
				const options = {credentials: 'same-origin'};
				if (fields) {
					options.method = 'POST';
					options.body = new URLSearchParams({
						...fields,
						csrfToken: pkp.currentUser.csrfToken,
					});
				}
				const response = await fetch(url, options);
				if (!response.ok) {
					throw new Error(labels.requestFailed);
				}
				const result = await response.json();
				if (!result.status) {
					throw new Error(typeof result.content === 'string' ? result.content : labels.requestFailed);
				}
				return result.content;
			}

			async function refresh() {
				loading.value = true;
				failed.value = false;
				try {
					const data = await request('fetch');
					if (!Array.isArray(data)) {
						throw new Error(labels.requestFailed);
					}
					reviews.value = data;
					loaded.value = true;
				} catch (e) {
					failed.value = true;
					notify(e.message || labels.requestFailed, 'warning');
				} finally {
					loading.value = false;
				}
			}

			function openForm(review = null) {
				openSideModal(ReviewFormModal, {review, saveReview: async (fields) => {
					const message = await request('save', fields);
					await refresh();
					notify(message, 'success');
				}});
			}

			async function remove(review) {
				if (!window.confirm(labels.confirmDelete)) {
					return;
				}
				loading.value = true;
				try {
					const message = await request('delete', {reviewId: review.review_id});
					await refresh();
					notify(message, 'success');
				} catch (e) {
					notify(e.message || labels.requestFailed, 'warning');
				} finally {
					loading.value = false;
				}
			}

			onMounted(refresh);

			function button(label, onClick, options = {}) {
				return h(Button, {onClick, isDisabled: loading.value, ...options}, {default: () => label});
			}

			return () => h('section', [
				loading.value && h('p', {role: 'status'}, labels.loading),
				(loaded.value || !failed.value) && h(ListPanel, {
					items: reviews.value.map((review) => ({...review, id: review.review_id})),
					emptyLabel: loading.value ? labels.loading : labels.empty,
				}, {
					header: () => h(Header, {}, {
						default: () => h('h3', labels.menu),
						actions: () => button(labels.add, () => openForm(), {isPrimary: true}),
					}),
					'item-title': ({item}) => item.link_name,
					'item-subtitle': ({item}) => h('div', [
						h('p', item.link),
						h('p', [item.reviewer, item.date].filter(Boolean).join(' — ')),
						h('p', {style: 'white-space: pre-wrap'}, item.money_quote),
					]),
					'item-actions': ({item}) => [
						button(labels.edit, () => openForm(item), {sizeVariant: 'compact'}),
						button(labels.delete, () => remove(item), {sizeVariant: 'compact', isWarnable: true}),
					],
				}),
			]);
		},
	});

	pkp.registry.storeExtend('workflow', ({store}) => {
		store.extender.extendFn('getMenuItems', (items, {submission}) => {
			if (!submission) {
				return items;
			}
			return [...items, {
				key: 'langsciReviews',
				label: labels.menu,
				icon: 'Dashboard',
				items: [{
					key: 'langsciReviews_manage',
					label: labels.manage,
					state: {primaryMenuItem: 'langsciReviews', title: labels.menu},
				}],
			}];
		});
		store.extender.extendFn('getPrimaryItems', (items, {selectedMenuState, submission}) => {
			if (selectedMenuState.primaryMenuItem !== 'langsciReviews' || !submission) {
				return items;
			}
			return [{component: 'LangsciReviewsEditor', props: {submissionId: submission.id}}];
		});
	});
})();
