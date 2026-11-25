const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const directory = path.resolve(__dirname, '../../src/view/adminhtml/web/js');
let attributeComponent;
let rowsComponent;
let ajax;
const alerts = [];
const jquery = () => ({val: () => 'form-key'});
jquery.ajax = (options) => ajax(options);
jquery.Deferred = () => {
    let state = 'pending';
    let value;
    const handlers = {resolved: [], rejected: []};
    const settle = (next, result) => {
        if (state === 'pending') {
            state = next;
            value = result;
            handlers[next].forEach((handler) => handler(value));
        }
        return deferred;
    };
    const on = (type, handler) => {
        if (handler) {
            state === type ? handler(value) : handlers[type].push(handler);
        }
    };
    const deferred = {
        resolve: (result) => settle('resolved', result),
        reject: () => settle('rejected'),
        done(handler) { on('resolved', handler); return deferred; },
        then(onResolved, onRejected) { on('resolved', onResolved); on('rejected', onRejected); return deferred; },
        promise: () => deferred,
        state: () => state
    };
    return deferred;
};
const registry = {get() {}};

function load(file, dependencies) {
    let result;
    vm.runInNewContext(fs.readFileSync(path.join(directory, file), 'utf8'), {
        define(names, factory) {
            result = factory(...dependencies);
        }
    });
    return result;
}

attributeComponent = load('form/element/filter-attribute.js', [
    {extend: (methods) => methods}, jquery, {}, registry
]);
rowsComponent = load('dynamic-rows/dynamic-rows.js', [
    {extend: (methods) => methods}, registry, {}, jquery,
    (message) => alerts.push(message), (message) => message
]);

function createRows(initialRecords) {
    let records = initialRecords;
    let reloads = 0;
    let changed = false;
    let spinner = false;
    let elements = [];
    const data = {
        'admin_url': '/admin/',
        'data.category_id': '42',
        'data.tweakwise_filter_template': '17',
        'data.store_id': '2'
    };
    const rows = {
        ...rowsComponent,
        source: {get: (key) => data[key], set: (key, value) => { data[key] = value; }},
        recordData(value) {
            if (value !== undefined) {
                records = value;
            }
            return records;
        },
        elems: () => elements,
        getChildItems: () => records,
        showSpinner: (value) => { spinner = value; },
        checkSpinner(elems) {
            this.showSpinner(!(!this.recordData().length || elems && elems.length === this.getChildItems().length));
        },
        reload() {
            reloads++;
            elements = [];
            this.showSpinner(true);
        },
        changed: (value) => { changed = value; }
    };
    return {
        rows, data,
        get records() { return records; },
        get reloads() { return reloads; },
        get changed() { return changed; },
        get spinner() { return spinner; }
    };
}

function deferredAjax() {
    let onSuccess;
    let onFailure;
    const request = {
        done(callback) { onSuccess = callback; return request; },
        fail(callback) { onFailure = callback; return request; },
        resolve(data) { onSuccess(data); },
        reject(xhr = {}) { onFailure(xhr); }
    };
    return request;
}

test('removes unavailable facets across pages and resolves custom facet names in one reload', () => {
    const fixture = createRows([
        {attribute: 'color'},
        {attribute: 'size'},
        {attribute: 'tw_other', attribute_other: 'old'},
        {attribute: 'tw_other', attribute_other: 'brand'},
        {attribute: ''}
    ]);

    fixture.rows.removeInvalidRecords([{value: 'brand'}]);

    expect(Array.from(fixture.records, (record) => record.attribute)).toEqual(['tw_other', '']);
    expect(fixture.reloads).toBe(1);
    expect(fixture.changed).toBe(true);
});

test('clears the spinner and notifies the user when cleanup removes every filter row', () => {
    const fixture = createRows([{attribute: 'color'}]);
    alerts.length = 0;

    fixture.rows.removeInvalidRecords([]);

    expect(fixture.records).toHaveLength(0);
    expect(fixture.reloads).toBe(1);
    expect(fixture.spinner).toBe(false);
    expect(alerts).toEqual([{
        content: 'Filter attributes that are no longer available for the selected category and template have been removed.'
    }]);
});

test('does not notify the user when cleanup retains every filter row', () => {
    const fixture = createRows([{attribute: 'color'}]);
    alerts.length = 0;

    fixture.rows.removeInvalidRecords([{value: 'color'}]);

    expect(fixture.reloads).toBe(0);
    expect(alerts).toHaveLength(0);
});

test('keeps the spinner until remaining filter rows finish rendering', () => {
    const fixture = createRows([{attribute: 'color'}, {attribute: 'size'}]);

    fixture.rows.removeInvalidRecords([{value: 'size'}]);

    expect(fixture.spinner).toBe(true);
    fixture.rows.checkSpinner(fixture.records);
    expect(fixture.spinner).toBe(false);
});

test('uses the selected category, template and store for validation', () => {
    const fixture = createRows([{attribute: 'color'}, {attribute: 'size'}]);
    const request = deferredAjax();
    let sent;
    ajax = (options) => { sent = options; return request; };

    fixture.rows.refreshAvailableFacets();
    expect(sent.url).toBe('/admin/tweakwise/ajax/cleanupfacets');
    expect(sent.data.category_id).toBe('42');
    expect(sent.data.filter_template).toBe('17');
    expect(sent.data.store_id).toBe('2');
    expect(sent.dataType).toBe('json');
    request.resolve([{value: 'size'}]);

    expect(Array.from(fixture.records, (record) => record.attribute)).toEqual(['size']);
});

test('failed validation preserves all records and warns the user', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    ajax = () => request;
    alerts.length = 0;

    fixture.rows.refreshAvailableFacets();
    request.reject();

    expect(fixture.records).toHaveLength(1);
    expect(fixture.reloads).toBe(0);
    expect(alerts).toHaveLength(1);
});

test('malformed validation results preserve all records and warn the user', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    ajax = () => request;
    alerts.length = 0;

    fixture.rows.refreshAvailableFacets();
    request.resolve({error: true});

    expect(fixture.records).toHaveLength(1);
    expect(fixture.reloads).toBe(0);
    expect(alerts).toHaveLength(1);
});

test('ignores a validation response after a newer request', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const oldRequest = deferredAjax();
    const newRequest = deferredAjax();
    let calls = 0;
    ajax = () => calls++ === 0 ? oldRequest : newRequest;

    fixture.rows.refreshAvailableFacets();
    fixture.rows.refreshAvailableFacets();
    oldRequest.resolve([]);
    newRequest.resolve([{value: 'color'}]);

    expect(fixture.records).toHaveLength(1);
    expect(fixture.reloads).toBe(0);
});

test('ignores a response for an old category', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    ajax = () => request;

    fixture.rows.refreshAvailableFacets();
    fixture.data['data.category_id'] = '43';
    request.resolve([]);

    expect(fixture.records).toHaveLength(1);
    expect(fixture.reloads).toBe(0);
});

test('does not replace a facet selected while a refresh is in flight', async () => {
    let value = 'color';
    let resolveRequest;
    const field = {
        ...attributeComponent,
        value: (next) => {
            if (next !== undefined) {
                value = next;
            }
            return value;
        },
        options: () => [{value: 'color'}, {value: 'size'}],
        source: {get: () => 'current'},
        fetchOptions: () => new Promise((resolve) => { resolveRequest = resolve; })
    };

    const refresh = field.refreshOptions();
    field.value('size');
    resolveRequest();
    await refresh;

    expect(value).toBe('size');
});

test('does not replace an unavailable facet before cleanup completes', () => {
    let value = 'color';
    const field = {
        value: (next) => { value = next; },
        options: () => [{value: 'size'}]
    };
    attributeComponent.restoreValue.call(field, 'color');
    expect(value).toBe('color');
});

test('blocks saving until the latest cleanup has completed successfully', () => {
    const formComponent = load('form/form.js', [
        {extend: (methods) => methods}, (message) => alerts.push(message), (message) => message, registry
    ]);
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    const save = jest.fn();
    const form = {...formComponent, source: fixture.rows.source, _super: save};
    ajax = () => request;

    fixture.rows.refreshAvailableFacets();
    form.save(true, {});
    expect(save).not.toHaveBeenCalled();
    request.resolve([{value: 'color'}]);
    form.save(true, {});
    expect(save).toHaveBeenCalledTimes(1);
});

test('does not save after a failed cleanup', () => {
    const retry = jest.fn(() => jquery.Deferred().promise());
    const findRows = jest.fn(() => ({refreshAvailableFacets: retry}));
    const formComponent = load('form/form.js', [
        {extend: (methods) => methods}, (message) => alerts.push(message), (message) => message,
        {get: findRows}
    ]);
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    const save = jest.fn();
    const form = {...formComponent, name: 'form.form', source: fixture.rows.source, _super: save};
    ajax = () => request;

    fixture.rows.refreshAvailableFacets();
    request.reject();
    form.save(true, {});
    expect(save).not.toHaveBeenCalled();
    expect(retry).toHaveBeenCalledTimes(1);
    expect(findRows).toHaveBeenCalledWith('form.form.general.filter_attributes');
});

test('subscribes to category and template changes before the first options response', () => {
    let value = 'color';
    const subscriptions = [];
    const field = {
        ...attributeComponent,
        _super() {},
        value(next) { if (next !== undefined) value = next; return value; },
        setOtherFieldVisibility() {},
        refreshOptions() {},
        subscribeAttribute() {},
        subscribeFilterTemplate() { subscriptions.push('template'); },
        subscribeCategoryId() { subscriptions.push('category'); }
    };

    field.initialize();
    expect(subscriptions).toEqual(['template', 'category']);
    expect(value).toBe('color');
});

test('restores the initial value only after the latest options request', async () => {
    let value = 'color';
    let options = [];
    const data = {'data.category_id': '42', 'data.tweakwise_filter_template': '17'};
    const requests = [];
    const restored = jest.fn();
    const field = {
        ...attributeComponent,
        initialOptionsPending: true,
        savedValue: 'color',
        value(next) { if (next !== undefined) value = next; return value; },
        options: () => options,
        source: {get: (key) => data[key]},
        fetchOptions() {
            const id = this.optionsRequestId = (this.optionsRequestId || 0) + 1;
            return new Promise((resolve) => requests.push((result) => {
                if (id === this.optionsRequestId) options = result;
                resolve();
            }));
        },
        setRestoredValue: restored,
        subscribeAttribute: jest.fn(),
        initAttributeValueField: jest.fn()
    };

    const oldRequest = field.refreshOptions();
    data['data.category_id'] = '43';
    const newRequest = field.refreshOptions();
    requests[0]([{value: 'old'}]);
    await oldRequest;
    expect(restored).not.toHaveBeenCalled();
    requests[1]([{value: 'color'}]);
    await newRequest;
    expect(restored).toHaveBeenCalledTimes(1);
    expect(field.options()).toEqual([{value: 'color'}]);
});

test('preserves a user selection made while initial options are loading', async () => {
    let value = 'color';
    let resolveRequest;
    const restored = jest.fn();
    const field = {
        ...attributeComponent,
        initialOptionsPending: true,
        savedValue: 'color',
        value(next) { if (next !== undefined) value = next; return value; },
        source: {get: () => 'current'},
        fetchOptions: () => new Promise((resolve) => { resolveRequest = resolve; }),
        setRestoredValue: restored,
        subscribeAttribute: jest.fn(),
        initAttributeValueField: jest.fn()
    };

    const request = field.refreshOptions();
    field.value('size');
    resolveRequest();
    await request;
    expect(restored).not.toHaveBeenCalled();
    expect(field.initAttributeValueField).toHaveBeenCalledWith('size');
});

test('restores the saved attribute when the value was cleared while initial options were loading', async () => {
    let value = 'color';
    let options = [];
    let resolveRequest;
    const field = {
        ...attributeComponent,
        initialOptionsPending: true,
        savedValue: 'color',
        value(next) { if (next !== undefined) value = next; return value; },
        options: () => options,
        source: {get: () => 'current'},
        fetchOptions: () => new Promise((resolve) => { resolveRequest = resolve; }),
        setOtherFieldVisibility: jest.fn(),
        subscribeAttribute: jest.fn(),
        initAttributeValueField: jest.fn()
    };

    const request = field.refreshOptions();
    options = [{value: 'size'}, {value: 'color'}];
    field.value('');
    resolveRequest();
    await request;
    expect(value).toBe('color');
    expect(field.initAttributeValueField).toHaveBeenCalledWith('color');
});

test('shows the server error message when the cleanup request fails', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const request = deferredAjax();
    ajax = () => request;
    alerts.length = 0;

    fixture.rows.refreshAvailableFacets();
    request.reject({responseJSON: {error: 'Unable to load the available Tweakwise filters.'}});

    expect(fixture.records).toHaveLength(1);
    expect(alerts).toHaveLength(1);
    expect(alerts[0].content).toContain('Unable to load the available Tweakwise filters.');
});

test('keeps the save blocked while a newer cleanup request is pending', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const oldRequest = deferredAjax();
    const newRequest = deferredAjax();
    let calls = 0;
    ajax = () => calls++ === 0 ? oldRequest : newRequest;

    fixture.rows.refreshAvailableFacets();
    fixture.rows.refreshAvailableFacets();
    oldRequest.resolve([]);
    expect(fixture.data['params.facetValidation']).toBe('pending');
    newRequest.resolve([{value: 'color'}]);
    expect(fixture.data['params.facetValidation']).toBe('valid');
});

test('rechecks instead of staying pending when the context changed during the check', () => {
    const fixture = createRows([{attribute: 'color'}]);
    const oldRequest = deferredAjax();
    const newRequest = deferredAjax();
    const sent = [];
    ajax = (options) => { sent.push(options); return sent.length === 1 ? oldRequest : newRequest; };

    fixture.rows.refreshAvailableFacets();
    fixture.data['data.store_id'] = '3';
    oldRequest.resolve([]);

    expect(fixture.records).toHaveLength(1);
    expect(sent).toHaveLength(2);
    expect(sent[1].data.store_id).toBe('3');
    newRequest.resolve([{value: 'color'}]);
    expect(fixture.data['params.facetValidation']).toBe('valid');
});

describe('saving after a failed cleanup', () => {
    function setup() {
        const fixture = createRows([{attribute: 'color'}]);
        const requests = [];
        ajax = () => { const request = deferredAjax(); requests.push(request); return request; };
        const formComponent = load('form/form.js', [
            {extend: (methods) => methods}, (message) => alerts.push(message), (message) => message,
            {get: () => fixture.rows}
        ]);
        const save = jest.fn();
        const form = {...formComponent, name: 'form.form', source: fixture.rows.source, _super: save};
        fixture.rows.refreshAvailableFacets();
        requests[0].reject();
        alerts.length = 0;
        form.save(true, {});
        return {fixture, requests, save};
    }

    test('retries without an immediate alert and saves when the retry succeeds', () => {
        const {requests, save} = setup();

        expect(requests).toHaveLength(2);
        expect(alerts).toHaveLength(0);
        requests[1].resolve([{value: 'color'}]);
        expect(save).toHaveBeenCalledTimes(1);
        expect(save).toHaveBeenCalledWith(true, {});
        expect(alerts).toHaveLength(0);
    });

    test('shows a single alert when the retry fails', () => {
        const {requests, save} = setup();

        requests[1].reject();
        expect(save).not.toHaveBeenCalled();
        expect(alerts).toHaveLength(1);
    });

    test('does not save when the retry removes filters', () => {
        const {fixture, requests, save} = setup();

        requests[1].resolve([]);
        expect(save).not.toHaveBeenCalled();
        expect(fixture.records).toHaveLength(0);
        expect(alerts).toHaveLength(1);
    });
});

test('requests attribute options for the store view of the page', () => {
    let sent;
    ajax = (options) => { sent = options; return {done: () => ({})}; };
    const data = {'admin_url': '/admin/', 'data.category_id': '42', 'data.tweakwise_filter_template': '17', 'data.store_id': '2'};
    const field = {...attributeComponent, source: {get: (key) => data[key]}, options() {}};

    field.fetchOptions();
    expect(sent.data.store_id).toBe('2');
});
