import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import fs from 'node:fs';

// The PHP side reads dist/hot to know the dev server is running.
function hotFile() {
	const file = 'dist/hot';
	return {
		name: 'hot-file',
		configureServer(server) {
			// Written once listening: the dev server also runs buildStart, which would delete it.
			server.httpServer?.once('listening', () => {
				fs.mkdirSync('dist', { recursive: true });
				fs.writeFileSync(file, 'http://localhost:5173');
			});
			const rm = () => fs.rmSync(file, { force: true });
			server.httpServer?.on('close', rm);
			process.on('exit', rm);
			process.on('SIGINT', () => process.exit());
			process.on('SIGTERM', () => process.exit());
		},
		buildStart() {
			fs.rmSync(file, { force: true });
		},
	};
}

export default defineConfig({
	base: './',
	plugins: [react(), tailwindcss(), hotFile()],
	build: {
		outDir: 'dist',
		manifest: true,
		rollupOptions: { input: 'src/main.jsx' },
		emptyOutDir: true,
	},
});
