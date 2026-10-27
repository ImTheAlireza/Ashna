// Compatibility shortcut for a 1× PNG preview.
import { renderCover } from './render.mjs';
import { tmpdir } from 'node:os';
import path from 'node:path';

await renderCover(1, process.argv[2] ?? path.join(tmpdir(), 'cover.png'));
