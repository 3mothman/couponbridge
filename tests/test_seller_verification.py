from pathlib import Path
import json, subprocess, socket, time, urllib.request, urllib.error, sys, os

work = Path(__file__).parent
php = Path(sys.argv[1]).resolve()
for stale in (work / '.runtime').glob('angebot-private-seller-*/*'):
    if stale.is_file():
        stale.unlink()
subprocess.run([str(php), str(work / 'seller-verification.php')], check=True)
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
base = f'http://127.0.0.1:{port}/'
process = subprocess.Popen([str(php), '-d', f'extension_dir={php.parent / "ext"}', '-d', 'extension=fileinfo', '-d', 'upload_max_filesize=12M', '-d', 'post_max_size=14M', '-S', f'127.0.0.1:{port}', '-t', str(work / '.runtime/public_html'), str(work / 'seller-verification.php')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, creationflags=subprocess.CREATE_NO_WINDOW if os.name == "nt" else 0)

def request(query, data=None, headers=None):
    try:
        with urllib.request.urlopen(urllib.request.Request(base + '?' + query, data, headers or {}), timeout=15) as response:
            return response.status, response.read()
    except urllib.error.HTTPError as error:
        return error.code, error.read()

def upload(filename, content, actor=4, nonce='test'):
    boundary = '----angebot-upload-test'
    body = (f'--{boundary}\r\nContent-Disposition: form-data; name="_wpnonce"\r\n\r\n{nonce}\r\n--{boundary}\r\nContent-Disposition: form-data; name="business_document"; filename="{filename}"\r\nContent-Type: application/octet-stream\r\n\r\n'.encode() + content + f'\r\n--{boundary}--\r\n'.encode())
    return request(f'action=upload&actor={actor}', body, {'Content-Type': f'multipart/form-data; boundary={boundary}'})

def expect(condition, message):
    if not condition:
        raise AssertionError(message)
    print('PASS', message)

try:
    for _ in range(50):
        try:
            request('action=state')
            break
        except OSError:
            time.sleep(.1)
    pdf = b'%PDF-1.4\nTEST BUSINESS DOCUMENT\n%%EOF\n'
    status, body = upload('business.pdf', pdf)
    expect(status == 200 and 'saved' in json.loads(body)['redirect'], 'real multipart PDF upload succeeds')
    state = json.loads(request('action=state')[1])
    expect(state['4'].get('_angebot_merchant_status') != 'approved', 'document upload does not approve seller')
    old_path = state['4']['_angebot_seller_document']['path']
    private_dirs = list((work / '.runtime').glob('angebot-private-seller-*'))
    expect(len(private_dirs) == 1 and (private_dirs[0] / old_path).is_file(), 'file stored outside public_html')
    expect(request('action=view&actor=4&user_id=4&_wpnonce=test')[1] == pdf, 'owner can download own document')
    expect(request('action=view&actor=3&user_id=4&_wpnonce=test')[1] == pdf, 'administrator can download seller document')
    expect(request('action=view&actor=1&user_id=4&_wpnonce=test')[0] == 403, 'another seller cannot download document')
    expect(request('action=view&actor=2&user_id=4&_wpnonce=test')[0] == 403, 'buyer cannot download seller document')
    expect(request('action=view&actor=0&user_id=4&_wpnonce=test')[0] == 403, 'anonymous visitor cannot download document')
    expect(request('action=view&actor=3&user_id=4&_wpnonce=wrong')[0] == 403, 'download requires valid nonce')
    expect(upload('business.pdf', pdf, nonce='wrong')[0] == 403, 'upload requires valid nonce')
    expect(upload('business.pdf', pdf, actor=2)[0] == 403, 'buyer cannot submit business upload')
    expect('error' in json.loads(upload('evil.php', b'<?php echo 1;')[1])['redirect'], 'executable extension rejected')
    expect('error' in json.loads(upload('fake.pdf', b'plain text disguised as PDF')[1])['redirect'], 'MIME mismatch rejected')
    expect('error' in json.loads(upload('large.pdf', pdf + b'X' * (8 * 1024 * 1024))[1])['redirect'], 'oversized upload rejected')
    expect((private_dirs[0] / old_path).is_file(), 'failed upload retains previous document')
    expect('saved' in json.loads(upload('replacement.pdf', pdf)[1])['redirect'], 'replacement upload succeeds')
    expect(not (private_dirs[0] / old_path).exists(), 'replacement removes old private file')
    expect(len(list(private_dirs[0].iterdir())) == 1, 'only latest document retained')
    print('All 18 local HTTP upload and access checks passed.')
finally:
    process.terminate()
    process.wait(timeout=10)
