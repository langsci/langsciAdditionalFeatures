const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const labels = Object.fromEntries(
	['menu', 'manage', 'add', 'edit', 'delete', 'cancel', 'save', 'confirmDelete', 'empty', 'loading', 'name', 'url', 'reviewer', 'date', 'quotation', 'requestFailed']
		.map((name) => [name, name]),
);
const urls = Object.fromEntries(['fetch', 'save', 'delete'].map((name) => [name, `https://example.test/${name}`]));
const formConfig = {
	id: 'langsciReviewsForm',
	fields: [
		{name: 'link_name', component: 'field-text'},
		{name: 'link', component: 'field-text'},
		{name: 'reviewer', component: 'field-text'},
		{name: 'date', component: 'field-text'},
		{name: 'money_quote', component: 'field-textarea'},
	],
};
let component;
let extend;
let mounted;
let modal;
let closed = false;
let failSave = false;
let closeAllowed;
let releaseSave;
let delaySave = false;
const extensions = {};
const calls = [];
const notifications = [];
const records = [{review_id: 3, link_name: 'Old review', link: 'https://example.test', date: '', reviewer: '', money_quote: ''}];
const h = (tag, props, children) => {
	if (children === undefined && (Array.isArray(props) || typeof props === 'string')) {
		children = props;
		props = {};
	}
	return {tag, props: props || {}, children};
};
const pkp = {
	currentUser: {csrfToken: 'test-token'},
	modules: {
		vue: {
			h,
			ref: (value) => ({value}),
			onMounted: (fn) => { mounted = fn; },
			inject: (name) => name === 'closeModal'
				? () => { if (!closeAllowed || closeAllowed()) closed = true; }
				: (callback) => { closeAllowed = callback; },
		},
		useModal: {useModal: () => ({openSideModal: (component, props) => { modal = {component, props}; }})},
		useNotify: {useNotify: () => ({notify: (message, type) => { notifications.push({message, type}); }})},
		useForm: {useForm: (config, {customSubmit}) => {
			const form = {value: {...config, customSubmit}};
			return {form, set: (id, changes) => Object.assign(form.value, changes)};
		}},
	},
	registry: {
		getComponent: (name) => name,
		registerComponent: (name, value) => { component = {name, value}; },
		storeExtend: (name, callback) => { assert.equal(name, 'workflow'); extend = callback; },
	},
};
const fetch = async (url, options) => {
	calls.push({url: String(url), options});
	if (delaySave && String(url).includes('/save')) {
		await new Promise((resolve) => { releaseSave = resolve; });
	}
	return {ok: true, json: async () => failSave && String(url).includes('/save')
		? {status: false, content: 'Invalid date'}
		: {status: true, content: options.method === 'POST' ? 'Saved' : records}};
};
const source = fs.readFileSync(path.join(__dirname, '../js/reviewsWorkflow.js'), 'utf8');
vm.runInNewContext(source, {
	pkp, fetch, URL, URLSearchParams,
	window: {langsciReviewsConfig: {labels, urls, form: formConfig}, location: {href: 'https://example.test/'}, confirm: () => true},
});
extend({store: {extender: {extendFn: (name, callback) => { extensions[name] = callback; }}}});
const original = [{key: 'workflow'}];
assert.equal(extensions.getMenuItems(original, {submission: null}), original);
const menu = extensions.getMenuItems(original, {submission: {id: 42}});
assert.equal(menu[1].key, 'langsciReviews');
const state = menu[1].items[0].state;
assert.equal(extensions.getPrimaryItems(original, {selectedMenuState: {}, submission: {id: 42}}), original);
const item = extensions.getPrimaryItems(undefined, {selectedMenuState: state, submission: {id: 42}})[0];
assert.equal(item.component, component.name);
assert.equal(item.props.submissionId, 42);

function find(node, predicate) {
	if (!node || typeof node !== 'object') return null;
	if (predicate(node)) return node;
	for (const child of Array.isArray(node.children) ? node.children.flat(Infinity) : [node.children]) {
		const result = find(child, predicate);
		if (result) return result;
	}
	return null;
}

(async () => {
	const render = component.value.setup({submissionId: 42});
	await mounted();
	assert.match(calls[0].url, /submissionId=42/);
	let panel = find(render(), (node) => node.tag === 'PkpListPanel');
	assert.equal(panel.props.items[0].link_name, 'Old review');
	const actions = panel.children['item-actions']({item: panel.props.items[0]});
	assert.equal(actions[0].children.default(), 'edit');
	assert.equal(actions[1].children.default(), 'delete');
	actions[0].props.onClick();
	assert(modal);
	const renderModal = modal.component.setup(modal.props);
	const modalBody = renderModal();
	assert.equal(modalBody.tag, 'PkpSideModalBody');
	assert.equal(modalBody.children.title(), 'edit');
	const layout = modalBody.children.default();
	assert.equal(layout.tag, 'PkpSideModalLayoutBasic');
	let form = find({children: layout.children.default()}, (node) => node.tag === 'PkpForm');
	assert(form);
	assert.equal(form.props.fields[0].component, 'field-text');
	assert.equal(form.props.fields[4].component, 'field-textarea');
	assert.equal(form.props.fields[0].value, 'Old review');
	form.props.onSet('langsciReviewsForm', {fields: form.props.fields.map((field) =>
		field.name === 'date' ? {...field, value: 'invalid'} : field,
	)});
	form = find({children: renderModal().children.default().children.default()}, (node) => node.tag === 'PkpForm');
	const values = Object.fromEntries(form.props.fields.map((field) => [field.name, field.value]));
	values.reviewId = form.props.hiddenFields.reviewId;
	failSave = true;
	const invalid = await form.props.customSubmit(values);
	assert.equal(invalid.data, null);
	const warning = find({children: renderModal().children.default().children.default()}, (node) => node.tag === 'PkpNotification');
	assert.equal(warning.props.type, 'warning');
	assert.equal(warning.children.default(), 'Invalid date');
	assert.equal(closed, false);
	assert.equal(closeAllowed(), true);
	assert.equal(find({children: renderModal().children.default().children.default()}, (node) => node.tag === 'PkpForm').props.fields[3].value, 'invalid');
	failSave = false;
	const success = await form.props.customSubmit(values);
	assert.equal(success.data.saved, true);
	form.props.onSuccess();
	assert.equal(closed, true);
	assert.equal(calls[2].options.method, 'POST');
	assert.equal(calls[2].options.body.get('csrfToken'), 'test-token');
	assert.equal(calls[2].options.body.get('reviewId'), '3');
	assert.equal(calls[2].options.body.get('link_name'), 'Old review');
	assert.equal(notifications[0].type, 'success');
	panel = find(render(), (node) => node.tag === 'PkpListPanel');
	await panel.children['item-actions']({item: panel.props.items[0]})[1].props.onClick();
	assert.equal(calls[4].options.body.get('reviewId'), '3');
	assert.equal(notifications[1].type, 'success');

	closed = false;
	panel.children.header().children.actions().props.onClick();
	const renderAdd = modal.component.setup(modal.props);
	assert.equal(renderAdd().children.title(), 'add');
	const addForm = find({children: renderAdd().children.default().children.default()}, (node) => node.tag === 'PkpForm');
	assert.equal(addForm.props.hiddenFields.reviewId, '');
	assert(addForm.props.fields.every((field) => field.value === ''));
	addForm.props.onCancel();
	assert.equal(closed, true);

	closed = false;
	delaySave = true;
	const pending = addForm.props.customSubmit({reviewId: '', link_name: 'New review', link: 'https://example.test', reviewer: '', date: '', money_quote: ''});
	assert.equal(closeAllowed(), false);
	addForm.props.onCancel();
	assert.equal(closed, false);
	releaseSave();
	await pending;
	assert.equal(closeAllowed(), true);
	console.log('Reviews workflow checks passed');
})().catch((error) => {
	console.error(error);
	process.exitCode = 1;
});
