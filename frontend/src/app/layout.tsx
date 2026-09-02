import type { Metadata, Viewport } from 'next';
import { IBM_Plex_Mono, IBM_Plex_Sans, Spectral } from 'next/font/google';

import Providers from './Providers';

import './globals.css';

/*
 * Three faces, each with one job (the canvas from prompt 17):
 *   prose — guidance, journal entries, oracle results: everything the user
 *           writes or is told. The reading face.
 *   ui    — labels, buttons, navigation. Chrome, never content.
 *   mono  — timestamps, dice notation, die faces. Anything the machine made.
 *
 * Prompt 18 asks for two faces. The mono is not decorative: the dice widget
 * aligns die faces in a row and every journal entry is stamped, both of which
 * need tabular, fixed-width digits.
 */
const prose = Spectral({
  subsets: ['latin'],
  weight: ['300', '400', '500', '600'],
  style: ['normal', 'italic'],
  variable: '--font-prose',
  display: 'swap',
  fallback: ['Iowan Old Style', 'Georgia', 'serif'],
});

const ui = IBM_Plex_Sans({
  subsets: ['latin'],
  weight: ['400', '500', '600'],
  variable: '--font-ui',
  display: 'swap',
  fallback: ['system-ui', '-apple-system', 'sans-serif'],
});

const mono = IBM_Plex_Mono({
  subsets: ['latin'],
  weight: ['400', '500'],
  variable: '--font-mono',
  display: 'swap',
  fallback: ['ui-monospace', 'Menlo', 'monospace'],
});

export const metadata: Metadata = {
  title: 'Lone Wolf — Solo TTRPG Assistant',
  description: 'Guided solo role-playing: flows, oracles, characters and dice.',
};

export const viewport: Viewport = {
  width: 'device-width',
  initialScale: 1,
  colorScheme: 'light dark',
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html
      lang="en"
      className={`${prose.variable} ${ui.variable} ${mono.variable}`}
    >
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
