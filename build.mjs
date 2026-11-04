// Builds the front-end assets into src/web/assets/frontend/dist.
// Run from the plugin root: npm install && npm run build
import * as esbuild from 'esbuild';

const src = 'src/web/assets/frontend/src';
const dist = 'src/web/assets/frontend/dist';

await esbuild.build({
    entryPoints: [`${src}/bootstrap.js`, `${src}/consentmate.js`, `${src}/consentmate.css`],
    outdir: dist,
    bundle: false,
    minify: true,
    target: ['es2018', 'safari13'],
    legalComments: 'none',
    logLevel: 'info',
});
