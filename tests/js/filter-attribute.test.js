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
    const data = {
        'admin_url': '/admin/',
        'data.category_id': '42',
        'data.tweakwise_filter_template': '17',
        'data.store_id': '2'
    };
    const rows = {
        ...rowsComponent,
        source: {get: (key) => data[key]},
        recordData(value) {
            if (value !== undefined) {
                records = value;
            }
            return records;
        },
        reload: () => reloads++,
        changed: (value) => { changed = value; }
    };
    return {rows, data, get records() { return records; }, get reloads() { return reloads; }, get changed() { return changed; }};
}

function deferredAjax() {
    let onSuccess;
    let onFailure;
    const request = {
        done(callback) { onSuccess = callback; return request; },
        fail(callback) { onFailure = callback; return request; },
        resolve(data) { onSuccess(data); },
        reject() { onFailure(); }
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
