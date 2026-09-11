# Nuxt SSR 映像（正式環境用；開發環境由 compose 直接跑 node:20-alpine + pnpm dev）

FROM node:20-alpine AS build
WORKDIR /app
RUN npm install -g pnpm@latest
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
RUN pnpm install --frozen-lockfile
COPY . .
RUN pnpm build

FROM node:20-alpine AS runtime
WORKDIR /app
ENV NODE_ENV=production \
    NITRO_HOST=0.0.0.0 \
    NITRO_PORT=3000
COPY --from=build /app/.output ./.output
EXPOSE 3000
USER node
CMD ["node", ".output/server/index.mjs"]
