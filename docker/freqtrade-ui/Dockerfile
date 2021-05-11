FROM node:15.8.0-alpine as ui-builder

RUN mkdir /app

RUN wget --quiet https://github.com/freqtrade/frequi/archive/0.0.6.tar.gz -O /tmp/ui.tar.gz \
    && tar xf /tmp/ui.tar.gz -C /app --strip 1 \
    && rm /tmp/ui.tar.gz

WORKDIR /app

RUN yarn
RUN yarn global add @vue/cli

COPY . /app
RUN yarn build

FROM nginx:1.19.6-alpine
COPY  --from=ui-builder /app/dist /etc/nginx/html
COPY  --from=ui-builder /app/nginx.conf /etc/nginx/nginx.conf
EXPOSE 80
CMD ["nginx"]
