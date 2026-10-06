import { fileURLToPath } from 'node:url';
import { defineConfig, passthroughImageService } from 'astro/config';
import starlight from '@astrojs/starlight';
import mermaid from 'astro-mermaid';
import starlightGitHubAlerts from 'starlight-github-alerts';
import starlightLinksValidator from 'starlight-links-validator';
import { remarkPlainMarkdown } from './src/remark-plain-markdown.mjs';

const base = '/laravel-visits';

export default defineConfig({
	site: 'https://fomvasss.github.io',
	base,
	// keeps the animated dashboard GIF as is instead of converting it to a static WebP
	image: { service: passthroughImageService() },
	markdown: {
		remarkPlugins: [[remarkPlainMarkdown, { root: fileURLToPath(new URL('.', import.meta.url)), base }]],
	},
	integrations: [
		mermaid(),
		starlight({
			title: 'Laravel Visits',
			description: 'Self-hosted, first-party analytics for Laravel: visitors, sessions, page views, conversions and a dashboard',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/fomvasss/laravel-visits' }],
			// the pages live in docs/ itself, not in src/content/docs/
			markdown: { processedDirs: ['.'] },
			expressiveCode: { shiki: { langAlias: { env: 'dotenv' } } },
			editLink: { baseUrl: 'https://github.com/fomvasss/laravel-visits/edit/master/docs/' },
			plugins: [starlightGitHubAlerts(), starlightLinksValidator()],
			sidebar: [
				{ label: 'Getting started', items: [{ label: 'Overview', slug: 'index' }, 'installation', 'configuration'] },
				{
					label: 'Usage',
					items: [
						'usage/how-it-works',
						'usage/page-views',
						'usage/custom-events',
						'usage/js-beacon',
						'usage/identity',
						'usage/reading-data',
						'usage/attribution',
						'usage/geo-device',
						'usage/consent',
						'usage/customization',
						'usage/dashboard',
						'usage/whoami',
						'usage/maintenance',
					],
				},
				{
					label: 'Guides',
					items: [
						'guides/production',
						'guides/client-integration',
						'guides/security',
						'guides/comparison',
						'guides/architecture',
					],
				},
				{
					label: 'Reference',
					items: [
						'reference/facade',
						'reference/models',
						'reference/database',
						'reference/routes',
						'reference/javascript',
						'reference/events',
						'reference/commands',
						'reference/contracts',
					],
				},
				'upgrading',
			],
		}),
	],
});
