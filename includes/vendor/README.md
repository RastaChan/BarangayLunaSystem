Document verification uses the PHP QR generator by Kazuhiko Arase:
https://github.com/kazuhikoarase/qrcode-generator/tree/master/php

Downloaded from the upstream master branch on 2026-10-09. Licensed under MIT.
Local compatibility adjustment: QRCode::createNullArray, createData and createBytes
are declared static for PHP 8. Capacity selection is handled by the portal helper.
