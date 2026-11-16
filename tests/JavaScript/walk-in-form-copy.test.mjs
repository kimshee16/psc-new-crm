import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const template = readFileSync(new URL('../../resources/views/crm/walk-in-forms.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];

function directory({ secure = true, clipboard, fallback = true, fallbackThrows = false } = {}) {
    let click;
    let selected = false;
    let fallbackCalls = 0;
    let focused = false;
    const input = {
        value: 'http://crm.test/intake/42',
        focus() {},
        select() { selected = true; },
        setSelectionRange(start, end) {
            assert.equal(start, 0);
            assert.equal(end, this.value.length);
        },
    };
    const status = { textContent: '' };
    const row = { querySelector: selector => selector === '[data-form-link]' ? input : status };
    const button = {
        hidden: false,
        addEventListener(event, handler) { assert.equal(event, 'click'); click = handler; },
        closest: () => row,
        focus() { focused = true; },
    };
    vm.runInNewContext(script, {
        window: { isSecureContext: secure },
        navigator: { clipboard },
        document: {
            querySelectorAll: () => [button],
            execCommand(command) {
                assert.equal(command, 'copy');
                assert.ok(selected, 'the form URL must be selected before copying');
                fallbackCalls++;
                if (fallbackThrows) throw new Error('Clipboard unavailable');
                return fallback;
            },
        },
    });
    return { click: () => click(), input, status, button, fallbackCalls: () => fallbackCalls, focused: () => focused };
}

test('secure clipboard copies the correct link and confirms success', async () => {
    let copied;
    const page = directory({ clipboard: { writeText: async value => { copied = value; } } });
    await page.click();
    assert.equal(copied, page.input.value);
    assert.equal(page.status.textContent, 'Link copied.');
    assert.equal(page.fallbackCalls(), 0);
    assert.equal(page.button.hidden, false);
    assert.ok(page.focused());
});

test('HTTP pages automatically copy using the selected URL fallback', async () => {
    const page = directory({ secure: false });
    await page.click();
    assert.equal(page.fallbackCalls(), 1);
    assert.equal(page.status.textContent, 'Link copied.');
    assert.equal(page.button.hidden, false);
});

test('denied clipboard permission falls back to automatic copying', async () => {
    const page = directory({ clipboard: { writeText: async () => { throw new Error('Permission denied'); } } });
    await page.click();
    assert.equal(page.fallbackCalls(), 1);
    assert.equal(page.status.textContent, 'Link copied.');
});

test('unsupported copying hides the button and gives manual copy guidance', async () => {
    const page = directory({ secure: false, fallback: false });
    await page.click();
    assert.equal(page.button.hidden, true);
    assert.match(page.status.textContent, /Copy the selected form link/);
    assert.doesNotMatch(page.status.textContent, /Link copied/);
});

test('clipboard exceptions do not display false success', async () => {
    const page = directory({ fallbackThrows: true });
    await page.click();
    assert.equal(page.button.hidden, true);
    assert.match(page.status.textContent, /Ctrl\+C/);
    assert.doesNotMatch(page.status.textContent, /Link copied/);
});
