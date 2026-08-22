# Frontend runtime: Node.js 22 LTS running the Next.js dev server (plan.md Phase 1).
FROM node:22-alpine

WORKDIR /app

# Dependency layer for cache-friendly rebuilds.
COPY package.json package-lock.json* ./
RUN npm ci --no-audit --no-fund || npm install --no-audit --no-fund

COPY . .

EXPOSE 3000

CMD ["npm", "run", "dev"]
