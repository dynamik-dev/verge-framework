// @ts-check
import { defineConfig } from 'astro/config';
import starlight from '@astrojs/starlight';
import tailwindcss from '@tailwindcss/vite';

// https://astro.build/config
export default defineConfig({
	site: 'https://verge.dev',
	vite: {
		plugins: [tailwindcss()],
	},
	integrations: [
		starlight({
			title: 'Verge',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/dynamik-dev/verge-framework' }],
			customCss: ['./src/styles/custom.css'],
			components: {
				SiteTitle: './src/components/Logo.astro',
			},
			sidebar: [
				{ label: 'Getting Started', slug: 'getting-started' },
				{ label: 'Introduction', slug: 'introduction' },
				{
					label: 'Modules',
					items: [
						{ label: 'Understanding Modules', slug: 'modules/understanding-modules' },
						{ label: 'Creating Modules', slug: 'modules/creating-modules' },
						{ label: 'Framework Bootstrap', slug: 'modules/framework-bootstrap' },
					],
				},
				{
					label: 'Routing',
					items: [
						{ label: 'Defining Routes', slug: 'guides/routing-basics' },
						{ label: 'Route Groups', slug: 'guides/grouping-routes' },
						{ label: 'Route Files', slug: 'guides/splitting-route-files' },
						{ label: 'Sub-Apps', slug: 'guides/mounting-sub-apps' },
						{ label: 'Inspecting Routes', slug: 'guides/inspecting-routes' },
					],
				},
				{
					label: 'Drivers',
					items: [
						{ label: 'Understanding Drivers', slug: 'guides/understanding-drivers' },
						{ label: 'Configuring Drivers', slug: 'guides/configuring-drivers' },
					],
				},
				{
					label: 'Controllers',
					items: [
						{ label: 'Using Controllers', slug: 'guides/using-controllers' },
					],
				},
				{
					label: 'Requests & Responses',
					items: [
						{ label: 'Handling Requests', slug: 'guides/handling-requests' },
						{ label: 'Returning Responses', slug: 'guides/returning-responses' },
					],
				},
				{
					label: 'Middleware',
					items: [
						{ label: 'Protecting Routes', slug: 'guides/protecting-routes' },
						{ label: 'Writing Middleware', slug: 'guides/writing-middleware' },
						{ label: 'Parameterized Middleware', slug: 'guides/parameterized-middleware' },
					],
				},
				{
					label: 'Using the Container',
					items: [
						{ label: 'Binding Services', slug: 'guides/binding-services' },
						{ label: 'Dependency Injection', slug: 'guides/injecting-dependencies' },
						{ label: 'Service Providers', slug: 'guides/configuring-providers' },
						{ label: 'Scoped Bindings', slug: 'guides/using-scoped-bindings' },
					],
				},
				{
					label: 'Handling Events',
					items: [
						{ label: 'Events', slug: 'guides/using-events' },
					],
				},
				{
					label: 'Caching',
					items: [
						{ label: 'Using the Cache', slug: 'guides/using-cache' },
					],
				},
				{
					label: 'Logging',
					items: [
						{ label: 'Logging Messages', slug: 'guides/logging' },
					],
				},
				{
					label: 'Configuration',
					items: [
						{ label: 'Environment Variables', slug: 'guides/reading-env-vars' },
					],
				},
				{
					label: 'Console',
					items: [
						{ label: 'Using the Console', slug: 'guides/using-console' },
					],
				},
				{
					label: 'Testing',
					items: [
						{ label: 'Testing Routes', slug: 'guides/testing-routes' },
						{ label: 'Mocking Dependencies', slug: 'guides/mocking-dependencies' },
					],
				},
				{
					label: 'Performance',
					items: [
						{ label: 'Bootstrap Caching', slug: 'guides/bootstrap-caching' },
					],
				},
				{
					label: 'Deployment',
					items: [
						{ label: 'FrankenPHP', slug: 'guides/deploying-frankenphp' },
					],
				},
				{
					label: 'API',
					autogenerate: { directory: 'reference' },
				},
			],
		}),
	],
});
