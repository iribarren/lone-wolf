import Link from 'next/link';

export default function Home() {
  return (
    <main style={{ fontFamily: 'system-ui', padding: '3rem' }}>
      <h1>Lone Wolf</h1>
      <p>Your solo table, always ready.</p>
      <ul>
        <li>
          <Link href="/campaigns">My campaigns</Link>
        </li>
        <li>
          <Link href="/campaigns/new">Start a new campaign</Link>
        </li>
      </ul>
    </main>
  );
}
