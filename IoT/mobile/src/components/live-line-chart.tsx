import { useEffect, useMemo, useState } from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  View,
  type LayoutChangeEvent,
} from 'react-native';

import { colors, spacing } from '@/theme/colors';

type ChartPoint = {
  label: string;
  value: number | null;
};

type LiveLineChartProps = {
  color: string;
  points: ChartPoint[];
  valueFormatter: (value: number) => string;
  minimum?: number;
  maximum?: number;
  threshold?: number;
  thresholdLabel?: string;
};

const CHART_HEIGHT = 156;
const PLOT_TOP = 12;
const PLOT_BOTTOM = 24;
const PLOT_HORIZONTAL = 8;

export function LiveLineChart({
  color,
  points,
  valueFormatter,
  minimum,
  maximum,
  threshold,
  thresholdLabel,
}: LiveLineChartProps) {
  const [width, setWidth] = useState(0);
  const [selectedIndex, setSelectedIndex] = useState(Math.max(points.length - 1, 0));

  useEffect(() => {
    setSelectedIndex(Math.max(points.length - 1, 0));
  }, [points.length]);

  const values = useMemo(
    () => points.map((point) => point.value).filter((value): value is number => value !== null),
    [points],
  );
  const observedMinimum = values.length ? Math.min(...values) : 0;
  const observedMaximum = values.length ? Math.max(...values) : 1;
  const rawMinimum = minimum ?? observedMinimum;
  const rawMaximum = maximum ?? observedMaximum;
  const padding = minimum === undefined && maximum === undefined
    ? Math.max((rawMaximum - rawMinimum) * 0.12, 0.5)
    : 0;
  const axisMinimum = rawMinimum - padding;
  const axisMaximum = Math.max(rawMaximum + padding, axisMinimum + 1);
  const plotWidth = Math.max(1, width - PLOT_HORIZONTAL * 2);
  const plotHeight = CHART_HEIGHT - PLOT_TOP - PLOT_BOTTOM;

  const coordinates = points.map((point, index) => {
    const x = PLOT_HORIZONTAL
      + (points.length <= 1 ? plotWidth / 2 : (index / (points.length - 1)) * plotWidth);
    const ratio = point.value === null
      ? 0
      : (point.value - axisMinimum) / (axisMaximum - axisMinimum);
    const y = PLOT_TOP + (1 - Math.max(0, Math.min(1, ratio))) * plotHeight;
    return { ...point, x, y };
  });
  const selected = coordinates[selectedIndex] ?? null;
  const thresholdRatio = threshold === undefined
    ? null
    : (threshold - axisMinimum) / (axisMaximum - axisMinimum);
  const thresholdTop = thresholdRatio === null
    ? 0
    : PLOT_TOP + (1 - Math.max(0, Math.min(1, thresholdRatio))) * plotHeight;

  function onLayout(event: LayoutChangeEvent) {
    setWidth(event.nativeEvent.layout.width);
  }

  return (
    <View>
      <View style={styles.readout}>
        <Text style={[styles.readoutValue, { color }]}>
          {selected?.value === null || selected?.value === undefined
            ? 'Sin dato'
            : valueFormatter(selected.value)}
        </Text>
        <Text style={styles.readoutTime}>{selected?.label ?? '--'}</Text>
      </View>
      <View onLayout={onLayout} style={styles.chart}>
        {[0, 0.5, 1].map((position) => (
          <View
            key={position}
            style={[styles.gridLine, { top: PLOT_TOP + position * plotHeight }]}
          />
        ))}

        {thresholdRatio !== null ? (
          <View style={[styles.thresholdLine, { top: thresholdTop }]}>
            <Text style={styles.thresholdText}>{thresholdLabel}</Text>
          </View>
        ) : null}

        {coordinates.slice(0, -1).map((point, index) => {
          const next = coordinates[index + 1];
          if (point.value === null || next.value === null) return null;
          const dx = next.x - point.x;
          const dy = next.y - point.y;
          const length = Math.sqrt(dx * dx + dy * dy);
          const angle = Math.atan2(dy, dx);
          return (
            <View
              key={`${point.label}-${index}-line`}
              style={[
                styles.segment,
                {
                  backgroundColor: color,
                  left: (point.x + next.x - length) / 2,
                  top: (point.y + next.y) / 2 - 1,
                  transform: [{ rotateZ: `${angle}rad` }],
                  width: length,
                },
              ]}
            />
          );
        })}

        {coordinates.map((point, index) => (
          <Pressable
            accessibilityLabel={
              point.value === null
                ? `Sin dato a las ${point.label}`
                : `${valueFormatter(point.value)} a las ${point.label}`
            }
            hitSlop={3}
            key={`${point.label}-${index}-point`}
            onPress={() => setSelectedIndex(index)}
            style={[
              styles.pointTarget,
              {
                left: point.x - 11,
                top: point.y - 11,
              },
            ]}
          >
            {point.value !== null ? (
              <View
                style={[
                  styles.point,
                  {
                    backgroundColor: index === selectedIndex ? colors.textStrong : color,
                    borderColor: color,
                  },
                ]}
              />
            ) : null}
          </Pressable>
        ))}

        <Text style={[styles.axisLabel, styles.axisMaximum]}>
          {valueFormatter(axisMaximum)}
        </Text>
        <Text style={[styles.axisLabel, styles.axisMinimum]}>
          {valueFormatter(axisMinimum)}
        </Text>
        <Text style={[styles.timeEdge, styles.timeStart]}>{points[0]?.label ?? '--'}</Text>
        <Text style={[styles.timeEdge, styles.timeEnd]}>{points.at(-1)?.label ?? '--'}</Text>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  readout: {
    alignItems: 'baseline',
    flexDirection: 'row',
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  readoutValue: {
    fontSize: 19,
    fontWeight: '900',
  },
  readoutTime: {
    color: colors.muted,
    fontSize: 11,
  },
  chart: {
    height: CHART_HEIGHT,
    overflow: 'hidden',
    position: 'relative',
  },
  gridLine: {
    backgroundColor: colors.borderSoft,
    height: StyleSheet.hairlineWidth,
    left: PLOT_HORIZONTAL,
    position: 'absolute',
    right: PLOT_HORIZONTAL,
  },
  thresholdLine: {
    borderStyle: 'dashed',
    borderTopColor: colors.critical,
    borderTopWidth: 1,
    left: PLOT_HORIZONTAL,
    position: 'absolute',
    right: PLOT_HORIZONTAL,
  },
  thresholdText: {
    alignSelf: 'flex-end',
    backgroundColor: colors.surface,
    color: colors.critical,
    fontSize: 9,
    fontWeight: '800',
    paddingLeft: spacing.xs,
    transform: [{ translateY: -12 }],
  },
  segment: {
    borderRadius: 1,
    height: 2,
    position: 'absolute',
  },
  pointTarget: {
    alignItems: 'center',
    height: 22,
    justifyContent: 'center',
    position: 'absolute',
    width: 22,
  },
  point: {
    borderRadius: 5,
    borderWidth: 2,
    height: 8,
    width: 8,
  },
  axisLabel: {
    backgroundColor: colors.surface,
    color: colors.muted,
    fontSize: 9,
    position: 'absolute',
    right: PLOT_HORIZONTAL,
  },
  axisMaximum: {
    top: PLOT_TOP,
  },
  axisMinimum: {
    bottom: PLOT_BOTTOM,
  },
  timeEdge: {
    bottom: 2,
    color: colors.muted,
    fontSize: 9,
    position: 'absolute',
  },
  timeStart: {
    left: PLOT_HORIZONTAL,
  },
  timeEnd: {
    right: PLOT_HORIZONTAL,
  },
});
