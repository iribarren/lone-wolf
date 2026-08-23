import type { Metadata } from 'next';

import Providers from './Providers';

export const metadata: Metadata = {
  title: 'Lone Wolf — Solo TTRPG Assistant',
  description: 'Guided solo role-playing: flows, oracles, characters and dice.',
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}
