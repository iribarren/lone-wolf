import Link from 'next/link';


import styles from './page.module.css';
export default function Home() {
  return (
    <main className={styles.landing}>
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
