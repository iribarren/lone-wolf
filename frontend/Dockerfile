# Frontend runtime: Node.js 22 LTS (Debian/glibc so host-installed linux-x64
# binaries in the bind-mounted node_modules stay compatible).
FROM node:22

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]
