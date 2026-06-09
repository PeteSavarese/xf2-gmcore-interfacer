FROM node:20-alpine AS pointshop_build

WORKDIR /work/pointshop

COPY gmod-pointshop/package.json gmod-pointshop/package-lock.json ./
RUN npm ci

COPY gmod-pointshop/ ./
RUN npm run build

ARG POINTSHOP_VERSION=
RUN POINTSHOP_VERSION="${POINTSHOP_VERSION:-$(node -p "require('./package.json').version")}" \
  && mkdir -p "/out/pointshop/${POINTSHOP_VERSION}" \
  && cp dist/index.html "/out/pointshop/${POINTSHOP_VERSION}/index.html" \
  && if [ -d dist/images ]; then cp -r dist/images "/out/pointshop/${POINTSHOP_VERSION}/images"; fi \
  && printf '{"current":"%s","version":"%s","url":"%s/index.html"}\n' \
    "${POINTSHOP_VERSION}" "${POINTSHOP_VERSION}" "${POINTSHOP_VERSION}" \
    > "/out/pointshop/manifest.json"

FROM nginx:1.29.2

RUN mkdir -p /var/www/html

COPY nginx/conf.d/ /etc/nginx/conf.d/
COPY nginx/maintenance.html /etc/nginx/maintenance.html

# In local dev docker compose bind-mounts will override these
COPY xenforo/ /var/www/html/

RUN rm -rf /var/www/html/scripts

COPY --from=pointshop_build /out/pointshop /var/www/html/pointshop

WORKDIR "/etc/nginx"

CMD ["nginx", "-g", "daemon off;"]
